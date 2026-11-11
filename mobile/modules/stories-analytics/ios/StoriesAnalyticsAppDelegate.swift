import ExpoModulesCore
import FirebaseCore

/// Khởi động Firebase ngay lúc app mở, trước khi JS chạy, để `first_open` / `session_start`
/// được ghi đúng lúc (Google Ads tính lượt cài từ `first_open`).
///
/// Thiếu GoogleService-Info.plist trong bundle (build quên đặt file) thì bỏ qua thay vì
/// để FirebaseApp.configure() làm sập app; khi đó mọi lệnh ghi sự kiện thành no-op.
public class StoriesAnalyticsAppDelegate: ExpoAppDelegateSubscriber {
  public func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil
  ) -> Bool {
    if FirebaseApp.app() == nil,
       Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist") != nil {
      FirebaseApp.configure()
    }
    return true
  }
}
