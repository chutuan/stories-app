package expo.modules.storiesanalytics

import android.os.Bundle
import com.google.firebase.analytics.FirebaseAnalytics
import expo.modules.kotlin.modules.Module
import expo.modules.kotlin.modules.ModuleDefinition

/** Cầu nối JS -> Firebase Analytics. Xem src/lib/analytics.ts cho danh sách sự kiện. */
class StoriesAnalyticsModule : Module() {
  // Build thiếu google-services.json thì FirebaseApp không khởi động và getInstance có thể
  // ném lỗi: khi đó mọi lệnh ghi thành no-op thay vì làm sập app.
  private val analytics: FirebaseAnalytics? by lazy {
    val context = appContext.reactContext ?: return@lazy null
    try {
      FirebaseAnalytics.getInstance(context)
    } catch (e: Exception) {
      null
    }
  }

  override fun definition() = ModuleDefinition {
    Name("StoriesAnalytics")

    Function("logEvent") { name: String, params: Map<String, Any?>? ->
      analytics?.logEvent(name, toBundle(params))
    }

    Function("logScreenView") { screenName: String ->
      analytics?.logEvent(
        FirebaseAnalytics.Event.SCREEN_VIEW,
        Bundle().apply {
          putString(FirebaseAnalytics.Param.SCREEN_NAME, screenName)
          putString(FirebaseAnalytics.Param.SCREEN_CLASS, screenName)
        }
      )
    }
  }

  /** Chỉ giữ chuỗi và số (Firebase nhận String/Long/Double). */
  private fun toBundle(params: Map<String, Any?>?): Bundle? {
    if (params.isNullOrEmpty()) return null
    val bundle = Bundle()
    for ((key, value) in params) {
      when (value) {
        is String -> bundle.putString(key, value)
        is Int -> bundle.putLong(key, value.toLong())
        is Long -> bundle.putLong(key, value)
        is Double ->
          if (value % 1.0 == 0.0) bundle.putLong(key, value.toLong()) else bundle.putDouble(key, value)
        is Number -> bundle.putDouble(key, value.toDouble())
      }
    }
    return if (bundle.isEmpty) null else bundle
  }
}
