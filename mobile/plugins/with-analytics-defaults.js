// @ts-check
/**
 * Tắt sẵn "tín hiệu cá nhân hoá quảng cáo" và đếm màn hình tự động của Google Analytics
 * trên Android.
 *
 * App chỉ chạy quảng cáo không cá nhân hoá (requestNonPersonalizedAdsOnly) và không xin
 * App Tracking Transparency, nên dữ liệu Analytics cũng không được dùng để nhắm quảng cáo
 * (remarketing). Đo lượt cài cho Google Ads vẫn chạy bình thường.
 *
 * Phía iOS là khoá GOOGLE_ANALYTICS_DEFAULT_ALLOW_AD_PERSONALIZATION_SIGNALS trong
 * ios.infoPlist của app.config.js; Android cần thẻ meta-data trong AndroidManifest, Expo
 * không có ô cấu hình sẵn nên dùng plugin này.
 */
const { AndroidConfig, withAndroidManifest } = require('expo/config-plugins');

/** @type {import('expo/config-plugins').ConfigPlugin} */
module.exports = function withAnalyticsDefaults(config) {
  return withAndroidManifest(config, (cfg) => {
    const app = AndroidConfig.Manifest.getMainApplicationOrThrow(cfg.modResults);
    AndroidConfig.Manifest.addMetaDataItemToMainApplication(
      app,
      'google_analytics_default_allow_ad_personalization_signals',
      'false',
    );
    // Màn hình do JS báo theo route; bản tự động chỉ thấy một MainActivity nên vô nghĩa.
    AndroidConfig.Manifest.addMetaDataItemToMainApplication(
      app,
      'google_analytics_automatic_screen_reporting_enabled',
      'false',
    );
    return cfg;
  });
};
