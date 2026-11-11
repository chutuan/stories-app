Pod::Spec.new do |s|
  s.name           = 'StoriesAnalytics'
  s.version        = '1.0.0'
  s.summary        = 'Firebase Analytics (Google Analytics) cho app Stories'
  s.license        = 'MIT'
  s.author         = 'Stories'
  s.homepage       = 'https://tunastory.com'
  s.platforms      = { :ios => '16.4' }
  s.swift_version  = '5.9'
  s.source         = { git: '' }
  s.static_framework = true

  s.dependency 'ExpoModulesCore'
  # Firebase Apple SDK 13+ chỉ phát hành qua Swift Package Manager; 12.x là dòng cuối có trên
  # CocoaPods. Ghim 12.x để KHÔNG phải chuyển cả app sang use_frameworks (React Native Firebase
  # v26 đòi static frameworks, v27 đòi dynamic frameworks + SPM — đổi cách link của mọi pod).
  # Subspec Core = KHÔNG kèm GoogleAppMeasurementIdentitySupport (phần đọc IDFA/AdSupport).
  # App không xin App Tracking Transparency nên IDFA luôn là số 0; Firebase đếm lượt cài/phiên
  # bằng app instance ID của riêng nó. ("Default" = Core + IdentitySupport.)
  s.dependency 'FirebaseAnalytics/Core', '~> 12.19.0'

  s.source_files = '**/*.swift'
  s.pod_target_xcconfig = {
    'DEFINES_MODULE' => 'YES',
    'SWIFT_COMPILATION_MODE' => 'wholemodule'
  }
end
