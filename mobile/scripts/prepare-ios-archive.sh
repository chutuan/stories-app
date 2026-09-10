#!/usr/bin/env bash
#
# Dựng lại thư mục ios/ để archive bằng Xcode, kèm mọi chỗ vá mà `expo prebuild`
# một mình KHÔNG làm. Bỏ sót bất kỳ bước nào dưới đây đều cho ra một bản .ipa
# TRÔNG NHƯ BÌNH THƯỜNG nhưng hỏng — không báo lỗi lúc build.
#
#   ./scripts/prepare-ios-archive.sh 3      # số build (CFBundleVersion)
#
# Sau khi chạy: mở ios/Stories.xcworkspace, chọn "Any iOS Device (arm64)",
# Product -> Archive.
#
# CHỈ dùng cho archive tại máy. `eas build` KHÔNG cần script này và cũng không
# được nhận thư mục ios/ do nó sinh ra — .easignore đã chặn sẵn.

set -euo pipefail

BUILD_NUMBER="${1:-}"
if [ -z "$BUILD_NUMBER" ]; then
  echo "Thiếu số build. Ví dụ: $0 3" >&2
  echo "Số này phải LỚN HƠN build cao nhất đã có trên App Store Connect." >&2
  exit 1
fi

cd "$(dirname "$0")/.."
ROOT="$PWD"

# CocoaPods đọc đường dẫn bằng unicode_normalize và sẽ nổ
# 'Unicode Normalization not appropriate for ASCII-8BIT' nếu locale không UTF-8.
export LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8

# ---- 1. Biến môi trường production ------------------------------------------
# eas.json > build.production.env chỉ có tác dụng trên EAS. Build tại máy phải
# lấy từ .env.production; thiếu file này thì ad ID rơi về ID TEST của Google.
if [ ! -f .env.production ]; then
  echo "Thiếu $ROOT/.env.production." >&2
  echo "Sinh lại từ eas.json > build.production.env (xem .env.example)." >&2
  exit 1
fi
set -a; . ./.env.production; set +a
export NODE_ENV=production

: "${EXPO_PUBLIC_API_URL:?thiếu EXPO_PUBLIC_API_URL trong .env.production}"
: "${EXPO_PUBLIC_ADMOB_IOS_APP_ID:?thiếu EXPO_PUBLIC_ADMOB_IOS_APP_ID}"
: "${EXPO_PUBLIC_ADMOB_BANNER_ID_IOS:?thiếu EXPO_PUBLIC_ADMOB_BANNER_ID_IOS}"
: "${EXPO_PUBLIC_ADMOB_REWARDED_ID_IOS:?thiếu EXPO_PUBLIC_ADMOB_REWARDED_ID_IOS}"

# ---- 2. Vá node_modules ------------------------------------------------------
# Xcode 26.3 không biên dịch được expo-modules-jsi — xem patches/README.md.
for p in patches/*.patch; do
  [ -e "$p" ] || continue
  if git apply --check "$p" 2>/dev/null; then
    git apply "$p" && echo "đã áp $p"
  else
    echo "bỏ qua $p (đã áp rồi hoặc không còn khớp)"
  fi
done

# ---- 3. Sinh lại thư mục native ---------------------------------------------
npx expo prebuild --platform ios --clean

# ---- 4. NODE_ENV cho các script phase của Expo -------------------------------
# expo-constants sinh EXConstants.bundle/app.config qua @expo/env.load(), mà
# @expo/env chọn file .env theo NODE_ENV. Xcode không đặt biến này nên nó lấy
# 'development' và BỎ QUA .env.production -> extra.apiUrl thành localhost và app
# chỉ hiện "Connection lost". Bundle JS không dính vì expo export:embed tự đặt
# NODE_ENV=production, nên lỗi rất dễ lọt: ad ID vẫn đúng, chỉ API là sai.
# prebuild --clean ghi đè .xcode.env.local nên phải thêm lại mỗi lần.
cat >> ios/.xcode.env.local <<'ENVSH'

if [ "$CONFIGURATION" = "Release" ]; then
  export NODE_ENV=production
fi
ENVSH

# ---- 5. Cài pods -------------------------------------------------------------
(cd ios && pod install)

# ---- 6. Số build -------------------------------------------------------------
# app.config.js cố ý KHÔNG khai ios.buildNumber (EAS quản lý từ xa), nên prebuild
# luôn ghi 1. Nộp trùng hoặc thấp hơn số đã có là bị App Store Connect từ chối.
plutil -replace CFBundleVersion -string "$BUILD_NUMBER" ios/Stories/Info.plist
sed -i '' "s/CURRENT_PROJECT_VERSION = .*;/CURRENT_PROJECT_VERSION = $BUILD_NUMBER;/g" \
  ios/Stories.xcodeproj/project.pbxproj

# ---- 7. Soát lại ------------------------------------------------------------
echo
echo "── Kiểm tra Info.plist ──"
P=ios/Stories/Info.plist
for k in CFBundleShortVersionString CFBundleVersion GADApplicationIdentifier; do
  printf '  %-30s %s\n' "$k" "$(plutil -extract "$k" raw -o - "$P")"
done
fail=0
for k in NSUserTrackingUsageDescription NSMicrophoneUsageDescription UIBackgroundModes; do
  if plutil -extract "$k" raw -o - "$P" >/dev/null 2>&1; then
    printf '  %-30s ✗ CÒN — phải gỡ trước khi nộp\n' "$k"; fail=1
  else
    printf '  %-30s ✓ đã gỡ\n' "$k"
  fi
done

echo
echo "Xong. Mở ios/Stories.xcworkspace -> Any iOS Device (arm64) -> Product -> Archive."
echo "SAU KHI ARCHIVE, mở gói .xcarchive và kiểm EXConstants.bundle/app.config:"
echo "  extra.apiUrl phải là $EXPO_PUBLIC_API_URL, KHÔNG được là localhost."
exit $fail
