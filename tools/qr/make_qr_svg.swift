// Tạo mã QR dạng SVG bằng CoreImage có sẵn trong macOS (không cần thư viện ngoài),
// rồi tự quét lại bằng CIDetector để chắc mã đọc ra đúng URL.
//
// Dùng: swift tools/qr/make_qr_svg.swift <url> <file.svg>
import CoreImage
import Foundation

let args = CommandLine.arguments
guard args.count == 3 else {
    FileHandle.standardError.write("usage: make_qr_svg.swift <url> <out.svg>\n".data(using: .utf8)!)
    exit(2)
}
let url = args[1], out = args[2]

// 1. Sinh ma trận QR: mỗi module là đúng 1 pixel. Mức sửa lỗi M (15%) đủ cho mã in nhỏ.
let gen = CIFilter(name: "CIQRCodeGenerator")!
gen.setValue(url.data(using: .utf8), forKey: "inputMessage")
gen.setValue("M", forKey: "inputCorrectionLevel")
let img = gen.outputImage!
let ctx = CIContext()
let cg = ctx.createCGImage(img, from: img.extent)!
let n = cg.width
var px = [UInt8](repeating: 0, count: n * n)
let gray = CGContext(data: &px, width: n, height: n, bitsPerComponent: 8, bytesPerRow: n,
                     space: CGColorSpaceCreateDeviceGray(), bitmapInfo: CGImageAlphaInfo.none.rawValue)!
gray.draw(cg, in: CGRect(x: 0, y: 0, width: n, height: n))

// 2. Xuất SVG: gộp các module đen liền nhau trên mỗi hàng thành một đoạn ngang cho gọn.
// CIQRCodeGenerator đã chừa sẵn lề trắng 1 module mỗi bên; SVG thêm nền trắng đặc để
// mã vẫn quét được trên giao diện tối.
var d = ""
for y in 0..<n {
    var x = 0
    while x < n {
        if px[y * n + x] < 128 {
            let start = x
            while x < n && px[y * n + x] < 128 { x += 1 }
            d += "M\(start) \(y)h\(x - start)v1h-\(x - start)z"
        } else {
            x += 1
        }
    }
}
let svg = """
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 \(n) \(n)" shape-rendering="crispEdges" role="img" aria-label="QR code: \(url)"><rect width="\(n)" height="\(n)" fill="#fff"/><path fill="#000" d="\(d)"/></svg>

"""
try! svg.write(toFile: out, atomically: true, encoding: .utf8)

// 3. Tự kiểm: phóng to (nearest-neighbor) rồi quét bằng CIDetector như một camera.
let big = img.samplingNearest().transformed(by: CGAffineTransform(scaleX: 12, y: 12))
let detector = CIDetector(ofType: CIDetectorTypeQRCode, context: ctx, options: [CIDetectorAccuracy: CIDetectorAccuracyHigh])!
let decoded = detector.features(in: big).compactMap { ($0 as? CIQRCodeFeature)?.messageString }
print("modules: \(n)x\(n) | svg bytes: \(svg.utf8.count) | decoded: \(decoded)")
if decoded != [url] {
    FileHandle.standardError.write("DECODE MISMATCH\n".data(using: .utf8)!)
    exit(1)
}
