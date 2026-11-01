import ExpoModulesCore
import UIKit

/// iPadOS 26 cho app chạy trong cửa sổ co giãn; cửa sổ có cụm nút đóng / thu nhỏ / phóng to
/// đè lên góc trên bên trái nội dung. `safeAreaInsets` (mà react-native-safe-area-context
/// đọc) KHÔNG tính cụm nút này, nên header tự vẽ của app nằm ngay dưới nó: nút Back bấm
/// trúng nút cửa sổ, banner quảng cáo đầu màn đọc bị che.
///
/// Module trả về phần lề trên phải chừa THÊM so với safe area để nội dung xuống dưới cụm
/// nút (cách "vertical corner adaptation" Apple hướng dẫn cho nội dung trải ngang mép trên).
/// Toàn màn hình, iPhone, hay iOS < 26: 0.
///
/// Cụm nút hiện / ẩn theo trạng thái cửa sổ, có khi trễ hơn lúc đổi cỡ, nên không đo theo
/// hẹn giờ mà gắn view dò vô hình: một view bám theo layout guide của vùng tránh cụm nút,
/// một view phủ kín cửa sổ. Guide đổi hoặc cửa sổ đổi cỡ -> view dò đổi cỡ -> gửi `onChange`.
public class WindowControlsModule: Module {
  private var probes: [UIView] = []
  private var lastSent: Double = -1

  public func definition() -> ModuleDefinition {
    Name("WindowControls")

    Events("onChange")

    AsyncFunction("getTopInset") { () -> Double in
      Self.extraTop(in: Self.hostView())
    }.runOnQueue(.main)

    OnStartObserving {
      DispatchQueue.main.async { self.installProbes() }
    }

    OnStopObserving {
      DispatchQueue.main.async {
        self.probes.forEach { $0.removeFromSuperview() }
        self.probes = []
        self.lastSent = -1
      }
    }
  }

  private static func hostView() -> UIView? {
    let windows = UIApplication.shared.connectedScenes
      .compactMap { $0 as? UIWindowScene }
      .flatMap { $0.windows }
    let window = windows.first { $0.isKeyWindow } ?? windows.first
    return window?.rootViewController?.view ?? window
  }

  private static func extraTop(in view: UIView?) -> Double {
    guard #available(iOS 26.0, *),
      UIDevice.current.userInterfaceIdiom == .pad,
      let view,
      let window = view.window
    else { return 0 }
    let safeTop = view.safeAreaInsets.top

    // Toàn màn hình: cụm nút nằm trong thanh menu ở giữa mép trên, không đè góc nội dung,
    // chỉ cần không chui dưới thanh trạng thái. Không hỏi guide ở đây: bấm nút phóng to từ
    // dạng cửa sổ, UIKit (iPadOS 26.3) giữ nguyên safe area và guide của dạng cửa sổ
    // (top 10 thay vì 32, guide vẫn tránh cụm nút) cho tới khi xoay máy.
    if fillsScreen(window) {
      let statusBar = window.windowScene?.statusBarManager?.statusBarFrame.height ?? 0
      return Double(max(0, statusBar - safeTop))
    }

    let adapted = view.edgeInsets(for: .safeArea(cornerAdaptation: .vertical))
    return Double(max(0, adapted.top - safeTop))
  }

  /// Cửa sổ phủ kín màn hình, kể cả dải thanh trạng thái. Cửa sổ thường (cả khi kéo to
  /// hết cỡ) luôn nằm dưới dải đó.
  private static func fillsScreen(_ window: UIWindow) -> Bool {
    guard let screen = window.windowScene?.screen else { return false }
    let frame = window.convert(window.bounds, to: screen.coordinateSpace)
    let bounds = screen.bounds
    return frame.minY <= 0.5
      && frame.width >= bounds.width - 0.5
      && frame.height >= bounds.height - 0.5
  }

  private func installProbes() {
    guard #available(iOS 26.0, *),
      UIDevice.current.userInterfaceIdiom == .pad,
      probes.isEmpty,
      let host = Self.hostView()
    else { return }

    let report: () -> Void = { [weak self, weak host] in self?.report(host) }

    // Trong suốt, không nhận chạm, nằm dưới cùng (sau nội dung React).
    // KHÔNG đặt isHidden: view ẩn có thể bị bỏ qua lượt layout, khi đó không báo gì.
    let guideProbe = SizeProbe(onResize: report)
    let windowProbe = SizeProbe(onResize: report)
    for probe in [guideProbe, windowProbe] {
      probe.backgroundColor = .clear
      probe.isUserInteractionEnabled = false
      probe.translatesAutoresizingMaskIntoConstraints = false
      host.insertSubview(probe, at: 0)
    }

    // Cao đúng bằng mép trên của vùng tránh cụm nút, rộng 1pt.
    let guide = host.layoutGuide(for: .safeArea(cornerAdaptation: .vertical))
    NSLayoutConstraint.activate([
      guideProbe.topAnchor.constraint(equalTo: host.topAnchor),
      guideProbe.leadingAnchor.constraint(equalTo: host.leadingAnchor),
      guideProbe.widthAnchor.constraint(equalToConstant: 1),
      guideProbe.bottomAnchor.constraint(equalTo: guide.topAnchor),
      // Phủ kín host: vào / ra toàn màn hình đổi cỡ cửa sổ dù guide có khi đứng yên.
      windowProbe.topAnchor.constraint(equalTo: host.topAnchor),
      windowProbe.leadingAnchor.constraint(equalTo: host.leadingAnchor),
      windowProbe.trailingAnchor.constraint(equalTo: host.trailingAnchor),
      windowProbe.bottomAnchor.constraint(equalTo: host.bottomAnchor),
    ])
    probes = [guideProbe, windowProbe]
  }

  private func report(_ host: UIView?) {
    send(Self.extraTop(in: host))
    // Lượt layout có thể chạy trước khi thanh trạng thái / safe area cập nhật xong: đo lại ở
    // vòng lặp kế tiếp.
    DispatchQueue.main.async { [weak self, weak host] in
      self?.send(Self.extraTop(in: host))
    }
  }

  private func send(_ top: Double) {
    guard top != lastSent else { return }
    lastSent = top
    sendEvent("onChange", ["top": top])
  }
}

/// Gọi `onResize` mỗi khi đổi cỡ (kể cả lần dựng đầu tiên).
private final class SizeProbe: UIView {
  private let onResize: () -> Void
  private var lastSize: CGSize?

  init(onResize: @escaping () -> Void) {
    self.onResize = onResize
    super.init(frame: .zero)
  }

  required init?(coder: NSCoder) {
    fatalError("init(coder:) has not been implemented")
  }

  override func layoutSubviews() {
    super.layoutSubviews()
    guard bounds.size != lastSize else { return }
    lastSize = bounds.size
    onResize()
  }
}
