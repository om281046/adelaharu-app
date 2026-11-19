import WidgetKit
import SwiftUI
import UIKit

/// Widgetul „Adela Haru” de pe ecranul principal (mic și mediu) și de pe ecranul blocat (dreptunghiular, iOS 16+).
/// Arată ce i-a dat aplicația prin AhApp.widget (AhWidgetDate, App Group): meditația zilei (titlu, text, poză) sau
/// faza ciclului (doar numele fazei și textul dat), ori starea neutră (lotusul și „Adela Haru”).
/// Widgetul nu descarcă nimic și nu citește nimic altceva: aplicația scrie datele și cere reîncărcarea.
/// Atingerea deschide aplicația cu adresa dată (link universal spre /spatiul-meu/#…), prin scene(_:openURLContexts:)
/// → App.addListener('appUrlOpen') din punte.

struct AhIntrare: TimelineEntry {
    let date: Date
    let d: AhWidgetDate
}

struct AhFurnizor: TimelineProvider {
    func placeholder(in context: Context) -> AhIntrare {
        AhIntrare(date: Date(), d: AhWidgetDate.gol)
    }

    func getSnapshot(in context: Context, completion: @escaping (AhIntrare) -> Void) {
        // în galeria de widgeturi: un exemplu neutru, fără datele ei
        if context.isPreview {
            completion(AhIntrare(date: Date(), d: AhWidgetDate.gol))
        } else {
            completion(AhIntrare(date: Date(), d: AhWidgetDate.citeste().deArata()))
        }
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<AhIntrare>) -> Void) {
        let acum = Date()
        let d = AhWidgetDate.citeste()
        var intrari = [AhIntrare(date: acum, d: d.deArata(acum: acum))]
        // când expiră datele (36 h), o a doua intrare trece singură pe starea neutră
        let expira = Date(timeIntervalSince1970: d.actualizat + AhWidgetDate.valabil)
        if d.mod != "nimic" && expira > acum {
            intrari.append(AhIntrare(date: expira, d: d.deArata(acum: expira.addingTimeInterval(1))))
        }
        // altfel nimic de reîmprospătat singur: aplicația cere reîncărcarea la fiecare AhApp.widget
        completion(Timeline(entries: intrari, policy: .never))
    }
}

enum AhPaleta {
    static let fundal = culoare("#FBF6F3")
    static let text = culoare("#4E3D34")
    static let bland = culoare("#7A6A60")
    static func culoare(_ hex: String) -> Color {
        let (r, g, b) = AhWidgetDate.rgb(hex)
        return Color(red: r, green: g, blue: b)
    }
    /// Titlul: Gilda Display nu e inclus în extensie (doar woff2 în pagina offline) ⇒ serif-ul sistemului (New York).
    static func titlu(_ marime: CGFloat) -> Font { .system(size: marime, weight: .regular, design: .serif) }
}

/// Lotusul aplicației (aceeași cale ca ic_stat_lotus / scripts/icoane.cjs, pe 24 × 24), desenat ca formă:
/// fără imagine în extensie, se colorează cu accentul dat și se vede clar la orice mărime.
struct AhLotus: Shape {
    func path(in r: CGRect) -> Path {
        let k = min(r.width, r.height) / 24
        let dx = r.midX - 12 * k, dy = r.midY - 12 * k
        func q(_ x: CGFloat, _ y: CGFloat) -> CGPoint { CGPoint(x: dx + x * k, y: dy + y * k) }
        var p = Path()
        p.move(to: q(12, 20))
        p.addCurve(to: q(3, 14), control1: q(7.5, 20), control2: q(4, 17.6))
        p.addCurve(to: q(12, 20), control1: q(5.6, 13.6), control2: q(8.6, 14.4))
        p.closeSubpath()
        p.move(to: q(12, 20))
        p.addCurve(to: q(21, 14), control1: q(16.5, 20), control2: q(20, 17.6))
        p.addCurve(to: q(12, 20), control1: q(18.4, 13.6), control2: q(15.4, 14.4))
        p.closeSubpath()
        p.move(to: q(12, 20))
        p.addCurve(to: q(9.6, 11), control1: q(9.8, 17.4), control2: q(9, 14.4))
        p.addCurve(to: q(12, 20), control1: q(11, 12), control2: q(12, 13.8))
        p.closeSubpath()
        p.move(to: q(12, 20))
        p.addCurve(to: q(14.4, 11), control1: q(14.2, 17.4), control2: q(15, 14.4))
        p.addCurve(to: q(12, 20), control1: q(13, 12), control2: q(12, 13.8))
        p.closeSubpath()
        p.move(to: q(12, 15.5))
        p.addCurve(to: q(12, 4.5), control1: q(10.6, 12.5), control2: q(10.7, 8.8))
        p.addCurve(to: q(12, 15.5), control1: q(13.3, 8.8), control2: q(13.4, 12.5))
        p.closeSubpath()
        return p
    }
}

struct AhSemn: View {
    let culoare: Color
    var latura: CGFloat = 18
    var body: some View {
        AhLotus()
            .stroke(culoare, style: StrokeStyle(lineWidth: max(1.2, latura / 14), lineCap: .round, lineJoin: .round))
            .frame(width: latura, height: latura)
            .accessibilityHidden(true)
    }
}

struct AhSemnatura: View {
    var body: some View {
        Text("Adela Haru")
            .font(.system(size: 10, weight: .regular, design: .serif))
            .tracking(1.2)
            .foregroundColor(AhPaleta.bland)
    }
}

struct AhVedere: View {
    @Environment(\.widgetFamily) var familie
    let intrare: AhIntrare

    private var d: AhWidgetDate { intrare.d }
    private var accent: Color { AhPaleta.culoare(d.culoare) }
    /// Faza ciclului e privată: pe ecranul blocat (telefon blocat) și în StandBy iOS o ascunde (.privacySensitive).
    private var privat: Bool { d.mod == "faza" }

    private var imagine: UIImage? {
        guard d.mod == "meditatie", d.areImagine, let u = AhWidgetDate.caleImagine else { return nil }
        return UIImage(contentsOfFile: u.path)
    }

    var body: some View {
        continut
            .widgetURL(URL(string: d.url) ?? URL(string: AhWidgetDate.acasa))
            .fundalWidget(esteAccesoriu ? Color.clear : AhPaleta.fundal, margini: !esteAccesoriu)
    }

    private var esteAccesoriu: Bool {
        if #available(iOS 16.0, *) { return familie == .accessoryRectangular }
        return false
    }

    @ViewBuilder private var continut: some View {
        if esteAccesoriu {
            accesoriu
        } else if d.mod == "nimic" {
            neutru
        } else if familie == .systemMedium {
            mediu
        } else {
            mic
        }
    }

    private var neutru: some View {
        VStack(spacing: 8) {
            Spacer(minLength: 0)
            AhSemn(culoare: accent, latura: familie == .systemMedium ? 44 : 40)
            Text("Adela Haru")
                .font(AhPaleta.titlu(familie == .systemMedium ? 18 : 16))
                .foregroundColor(AhPaleta.text)
            Spacer(minLength: 0)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .combine)
    }

    private var texte: some View {
        VStack(alignment: .leading, spacing: 4) {
            if !d.titlu.isEmpty {
                Text(d.titlu)
                    .font(AhPaleta.titlu(familie == .systemMedium ? 19 : 17))
                    .foregroundColor(AhPaleta.text)
                    .lineLimit(3)
                    .minimumScaleFactor(0.8)
            }
            if !d.sub.isEmpty {
                Text(d.sub)
                    .font(.system(size: 12))
                    .foregroundColor(AhPaleta.bland)
                    .lineLimit(familie == .systemMedium ? 3 : 2)
            }
        }
        .privatDaca(privat)
    }

    private var mic: some View {
        VStack(alignment: .leading, spacing: 0) {
            AhSemn(culoare: accent)
            Spacer(minLength: 6)
            texte
            Spacer(minLength: 6)
            AhSemnatura()
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
    }

    private var mediu: some View {
        HStack(spacing: 14) {
            if let img = imagine {
                Image(uiImage: img)
                    .resizable()
                    .scaledToFill()
                    .frame(width: 110, height: 110)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                    .accessibilityHidden(true)
            }
            VStack(alignment: .leading, spacing: 0) {
                HStack(spacing: 6) {
                    AhSemn(culoare: accent)
                    Spacer(minLength: 0)
                }
                Spacer(minLength: 6)
                texte
                Spacer(minLength: 6)
                AhSemnatura()
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
        }
    }

    @ViewBuilder private var accesoriu: some View {
        if d.mod == "nimic" {
            HStack(spacing: 6) {
                AhSemn(culoare: .primary, latura: 20).widgetAccentableDacaSe()
                Text("Adela Haru").font(AhPaleta.titlu(15))
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        } else {
            VStack(alignment: .leading, spacing: 1) {
                Text(d.titlu).font(.headline).lineLimit(1).widgetAccentableDacaSe()
                if !d.sub.isEmpty {
                    Text(d.sub).font(.caption).lineLimit(2)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .privatDaca(privat)
        }
    }
}

extension View {
    /// iOS 17+ cere containerBackground (altfel widgetul arată „Please adopt containerBackground API”);
    /// înainte, fundalul și marginile le punem noi.
    @ViewBuilder func fundalWidget(_ c: Color, margini: Bool) -> some View {
        if #available(iOS 17.0, *) {
            self.containerBackground(for: .widget) { c }
        } else if margini {
            self.padding(16).background(c)
        } else {
            self
        }
    }

    @ViewBuilder func privatDaca(_ p: Bool) -> some View {
        if p {
            self.privacySensitive()   // iOS 15+ (ținta minimă a extensiei)
        } else {
            self
        }
    }

    @ViewBuilder func widgetAccentableDacaSe() -> some View {
        if #available(iOS 16.0, *) {
            self.widgetAccentable()
        } else {
            self
        }
    }
}

@main
struct AdelaHaruWidget: Widget {
    var body: some WidgetConfiguration {
        StaticConfiguration(kind: AhWidgetDate.tip, provider: AhFurnizor()) { intrare in
            AhVedere(intrare: intrare)
        }
        .configurationDisplayName("Adela Haru")
        .description("Meditația zilei sau faza ciclului")
        .supportedFamilies(AdelaHaruWidget.familii)
    }

    static var familii: [WidgetFamily] {
        if #available(iOS 16.0, *) {
            return [.systemSmall, .systemMedium, .accessoryRectangular]
        }
        return [.systemSmall, .systemMedium]
    }
}
