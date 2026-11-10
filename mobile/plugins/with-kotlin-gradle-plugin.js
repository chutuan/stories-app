// @ts-check
/**
 * Ghim phiên bản Kotlin Gradle Plugin (KGP) ở android/build.gradle gốc.
 *
 * Vì sao cần: template Expo SDK 57 khai `classpath('org.jetbrains.kotlin:kotlin-gradle-plugin')`
 * KHÔNG có số phiên bản, nên KGP lấy theo @react-native/gradle-plugin (Kotlin 2.1.20).
 * `kotlinVersion` của expo-build-properties chỉ đặt rootProject.ext.kotlinVersion, còn trình
 * biên dịch Kotlin thật vẫn là 2.1.20. Mà play-services-ads 25.4 (react-native-google-mobile-ads
 * 16.4) và kotlin-stdlib 2.3.0 mang metadata Kotlin 2.3.0, nên build EAS ngày 09/11/2026 vỡ ở
 * :react-native-google-mobile-ads:compileReleaseKotlin ("incompatible version of Kotlin ...
 * expected version is 2.1.0") và :react-native-safe-area-context:compileReleaseKotlin.
 *
 * Gradle chọn bản cao nhất khi hai nơi cùng khai KGP, nên ghi số phiên bản ở đây là đủ.
 * Khi nâng SDK mà template đã tự khai phiên bản >= giá trị này thì có thể gỡ plugin.
 */
const { withProjectBuildGradle } = require('expo/config-plugins');

/** @type {import('expo/config-plugins').ConfigPlugin<{ version: string }>} */
module.exports = function withKotlinGradlePlugin(config, { version }) {
  return withProjectBuildGradle(config, (cfg) => {
    const pattern = /classpath\((['"])org\.jetbrains\.kotlin:kotlin-gradle-plugin(?::[^'"]*)?\1\)/;
    if (!pattern.test(cfg.modResults.contents)) {
      throw new Error(
        'with-kotlin-gradle-plugin: không tìm thấy classpath kotlin-gradle-plugin trong android/build.gradle',
      );
    }
    cfg.modResults.contents = cfg.modResults.contents.replace(
      pattern,
      `classpath('org.jetbrains.kotlin:kotlin-gradle-plugin:${version}')`,
    );
    return cfg;
  });
};
