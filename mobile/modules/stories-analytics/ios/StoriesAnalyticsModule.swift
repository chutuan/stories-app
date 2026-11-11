import ExpoModulesCore
import FirebaseAnalytics
import FirebaseCore

/// Cầu nối JS -> Firebase Analytics. Xem src/lib/analytics.ts cho danh sách sự kiện.
public class StoriesAnalyticsModule: Module {
  public func definition() -> ModuleDefinition {
    Name("StoriesAnalytics")

    /// Sự kiện tuỳ ý. Tham số chỉ nhận chuỗi và số (JS đã lọc).
    Function("logEvent") { (name: String, params: [String: Any]?) in
      guard FirebaseApp.app() != nil else { return }
      Analytics.logEvent(name, parameters: Self.clean(params))
    }

    /// Màn hình đang xem. React Native chỉ có một UIViewController nên Firebase không tự
    /// đếm màn được; JS báo theo route của expo-router.
    Function("logScreenView") { (screenName: String) in
      guard FirebaseApp.app() != nil else { return }
      Analytics.logEvent(AnalyticsEventScreenView, parameters: [
        AnalyticsParameterScreenName: screenName,
        AnalyticsParameterScreenClass: screenName,
      ])
    }
  }

  /// Giữ lại chuỗi và số; bỏ mọi kiểu khác (Firebase chỉ nhận NSString/NSNumber).
  private static func clean(_ params: [String: Any]?) -> [String: Any]? {
    guard let params else { return nil }
    var out: [String: Any] = [:]
    for (key, value) in params {
      if let s = value as? String {
        out[key] = s
      } else if let n = value as? NSNumber {
        out[key] = n
      }
    }
    return out.isEmpty ? nil : out
  }
}
