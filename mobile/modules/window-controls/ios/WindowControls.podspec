Pod::Spec.new do |s|
  s.name           = 'WindowControls'
  s.version        = '1.0.0'
  s.summary        = 'Lề trên cho cụm nút cửa sổ của iPadOS 26'
  s.license        = 'MIT'
  s.author         = 'Stories'
  s.homepage       = 'https://tunastory.com'
  s.platforms      = { :ios => '16.4' }
  s.swift_version  = '5.9'
  s.source         = { git: '' }
  s.static_framework = true

  s.dependency 'ExpoModulesCore'

  s.source_files = '**/*.swift'
  s.pod_target_xcconfig = {
    'DEFINES_MODULE' => 'YES',
    'SWIFT_COMPILATION_MODE' => 'wholemodule'
  }
end
