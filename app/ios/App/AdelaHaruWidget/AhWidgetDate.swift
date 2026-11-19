import Foundation

/// Datele widgetului de pe ecranul principal: le scrie aplicația (AhApp.widget, din puntea site-ului) și le citește
/// extensia AdelaHaruWidget. Fișierul e compilat în AMBELE ținte (App și AdelaHaruWidget), ca formatul să fie unul singur.
/// Drumul: App Group `group.ro.adelaharu.app` → UserDefaults(suiteName:) pentru text, containerul grupului pentru poză
/// (widgetul nu poate descărca singur sigur: WidgetKit îi dă puțin timp și puțină memorie).
///
/// Confidențialitate: la mod = "faza" se păstrează DOAR numele fazei (titlu) și textul dat (sub), fără poză și fără
/// altceva; la mod = "nimic" nu se păstrează niciun text (widgetul arată doar lotusul și „Adela Haru”).
struct AhWidgetDate: Codable, Equatable {
    var mod: String        // "meditatie" | "faza" | "nimic"
    var titlu: String
    var sub: String
    var culoare: String    // "#RRGGBB"
    var areImagine: Bool
    var url: String
    var actualizat: Double // secunde de la 1970; după `valabil` widgetul trece singur pe starea neutră

    static let grup = "group.ro.adelaharu.app"
    static let cheie = "ah_widget"
    static let fisierImagine = "widget-imagine.jpg"
    static let tip = "AdelaHaruWidget"                       // kind-ul widgetului (WidgetCenter.reloadTimelines)
    static let accent = "#964E46"                            // accentul Spațiului meu (sn235)
    static let acasa = "https://www.adelaharu.ro/spatiul-meu/"
    static let gazde: Set<String> = ["www.adelaharu.ro", "adelaharu.ro"]
    /// „Meditația zilei” și faza sunt ale unei zile: după 36 h fără o deschidere a aplicației, widgetul nu mai arată
    /// ceva vechi (o fază greșită), ci starea neutră.
    static let valabil: Double = 36 * 3600
    static let maxTitlu = 80
    static let maxSub = 140

    static let gol = AhWidgetDate(mod: "nimic", titlu: "", sub: "", culoare: accent, areImagine: false, url: acasa, actualizat: 0)

    static var depozit: UserDefaults? { UserDefaults(suiteName: grup) }
    static var folder: URL? { FileManager.default.containerURL(forSecurityApplicationGroupIdentifier: grup) }
    static var caleImagine: URL? { folder?.appendingPathComponent(fisierImagine) }

    static func citeste() -> AhWidgetDate {
        guard let d = depozit?.data(forKey: cheie), let w = try? JSONDecoder().decode(AhWidgetDate.self, from: d) else { return gol }
        return w
    }

    func scrie() {
        guard let d = try? JSONEncoder().encode(self) else { return }
        AhWidgetDate.depozit?.set(d, forKey: AhWidgetDate.cheie)
    }

    /// Starea de arătat acum: datele vechi (sau un mod necunoscut) devin starea neutră, cu aceeași adresă.
    func deArata(acum: Date = Date()) -> AhWidgetDate {
        if mod == "nimic" { return self }
        if mod != "meditatie" && mod != "faza" { return AhWidgetDate.gol }
        if actualizat <= 0 || acum.timeIntervalSince1970 - actualizat > AhWidgetDate.valabil {
            var g = AhWidgetDate.gol
            g.culoare = culoare
            return g
        }
        return self
    }

    /// Curăță ce vine din pagină. Funcție pură (fără disc), aceeași în aplicație și în widget.
    static func curata(mod: String?, titlu: String?, sub: String?, culoare: String?, url: String?, acum: Date = Date()) -> AhWidgetDate {
        let m = ["meditatie", "faza", "nimic"].contains(mod ?? "") ? mod! : "nimic"
        var w = AhWidgetDate.gol
        w.mod = m
        w.culoare = culoareSigura(culoare)
        w.url = adresaSigura(url)
        w.actualizat = acum.timeIntervalSince1970
        if m != "nimic" {
            w.titlu = scurteaza(titlu, maxTitlu)
            w.sub = scurteaza(sub, maxSub)
            if w.titlu.isEmpty && w.sub.isEmpty { w.mod = "nimic" }
        }
        return w
    }

    static func scurteaza(_ s: String?, _ n: Int) -> String {
        let t = (s ?? "").components(separatedBy: .newlines).joined(separator: " ").trimmingCharacters(in: .whitespacesAndNewlines)
        return t.count <= n ? t : String(t.prefix(n - 1)).trimmingCharacters(in: .whitespaces) + "…"
    }

    /// Doar "#RGB" / "#RRGGBB"; altfel accentul.
    static func culoareSigura(_ s: String?) -> String {
        let t = (s ?? "").trimmingCharacters(in: .whitespaces)
        let hex = t.hasPrefix("#") ? String(t.dropFirst()) : ""
        guard hex.count == 3 || hex.count == 6, hex.allSatisfy({ $0.isHexDigit }) else { return accent }
        let lung = hex.count == 3 ? hex.map { "\($0)\($0)" }.joined() : hex
        return "#" + lung.uppercased()
    }

    /// Atingerea deschide doar o pagină a site-ului (link universal /spatiul-meu/#…); orice altceva → Spațiul meu.
    static func adresaSigura(_ s: String?) -> String {
        guard let s = s, let u = URLComponents(string: s), u.scheme?.lowercased() == "https",
              let h = u.host?.lowercased(), gazde.contains(h), u.user == nil, u.password == nil, u.port == nil else { return acasa }
        return u.string ?? acasa
    }

    /// "#RRGGBB" → (r, g, b) între 0 și 1.
    static func rgb(_ hex: String) -> (Double, Double, Double) {
        let h = culoareSigura(hex).dropFirst()
        let v = UInt32(h, radix: 16) ?? 0x964E46
        return (Double((v >> 16) & 0xFF) / 255, Double((v >> 8) & 0xFF) / 255, Double(v & 0xFF) / 255)
    }
}
