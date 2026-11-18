#!/usr/bin/env bash
# Push pe iPhone: notele Apple (docs/store/review-notes.md, funcția 5) și fișa App Store promit notificări.
# Ca să fie adevărat, build-ul iOS are nevoie de toate trei:
#   1. `aps-environment` = production în App.entitlements (fără el, APNs nu dă token);
#   2. SDK-ul Firebase Messaging (SPM `firebase-ios-sdk`) în proiect (fără el AhApp.info().push = false);
#   3. GoogleService-Info.plist ca fișier în ios/App/App/ (nevid, plist valid) și faza „GoogleService-Info.plist (dacă
#      există)” din proiect, care îl copiază în aplicație. NU căutăm doar numele în project.pbxproj: el apare mereu acolo,
#      ca nume al fazei, deci verificarea ar trece și fără fișier (review 18 nov.).
# AH_PUSH_IOS=1 (implicit) = versiunea promite push pe iPhone. Atunci, la încărcare în TestFlight (AH_UPLOAD=1),
# lipsa oricăreia OPREȘTE construirea; fără încărcare, doar avertizează.
# AH_PUSH_IOS=0 = versiune fără push pe iPhone: trece, dar amintește ce se scoate din note și din fișă.
# Folosire: bash verifica-push-ios.sh [folderul app]   (implicit: folderul curent)
set -uo pipefail

app="${1:-.}"
push="${AH_PUSH_IOS:-1}"
upload="${AH_UPLOAD:-0}"
ent="$app/ios/App/App/App.entitlements"
pbx="$app/ios/App/App.xcodeproj/project.pbxproj"
gsi="$app/ios/App/App/GoogleService-Info.plist"
faza_gsi="A1D0C0DE2F00000000000017" # id-ul fazei de copiere din project.pbxproj
spm="$app/ios/App/CapApp-SPM/Package.swift"

lipsa=()
if [ ! -f "$ent" ] || ! grep -A1 "<key>aps-environment</key>" "$ent" | grep -q "<string>production</string>"; then
  lipsa+=("aps-environment=production în ios/App/App/App.entitlements")
fi
if ! { [ -f "$pbx" ] && grep -q "firebase-ios-sdk" "$pbx"; } && ! { [ -f "$spm" ] && grep -q "firebase-ios-sdk" "$spm"; }; then
  lipsa+=("pachetul SPM firebase-ios-sdk (FirebaseMessaging) în proiect")
fi
# plist valid: plutil pe macOS, plistlib din Python în rest (Linux)
plist_valid() {
  if command -v plutil >/dev/null 2>&1; then plutil -lint "$1" >/dev/null 2>&1; return $?; fi
  if command -v python3 >/dev/null 2>&1; then python3 -I -c 'import plistlib,sys; plistlib.load(open(sys.argv[1],"rb"))' "$1" >/dev/null 2>&1; return $?; fi
  grep -q "<plist" "$1"
}
if [ ! -s "$gsi" ]; then
  lipsa+=("fișierul ios/App/App/GoogleService-Info.plist (secretul GOOGLE_SERVICE_INFO_PLIST)")
elif ! plist_valid "$gsi"; then
  lipsa+=("un GoogleService-Info.plist valid (fișierul din ios/App/App/ nu se citește ca plist)")
fi
if ! { [ -f "$pbx" ] && grep -q "$faza_gsi /\* GoogleService-Info.plist" "$pbx" && grep -q 'App/GoogleService-Info.plist' "$pbx"; }; then
  lipsa+=("faza de copiere „GoogleService-Info.plist (dacă există)” ($faza_gsi) în App.xcodeproj")
fi

if [ "$push" != "1" ]; then
  echo "::notice::Versiune FĂRĂ push pe iPhone (push_ios debifat): scoateți funcția 5 din notele Apple și rândul despre notificări din fișa App Store (docs/release.md, Ce mai trebuie)."
  exit 0
fi
if [ "${#lipsa[@]}" -eq 0 ]; then
  echo "Push pe iPhone: aps-environment, Firebase Messaging, GoogleService-Info.plist (fișier valid) și faza care îl copiază sunt în proiect."
  exit 0
fi
for x in "${lipsa[@]}"; do
  if [ "$upload" = "1" ]; then echo "::error::Push pe iPhone promis în note, dar lipsește: $x"; else echo "::warning::Push pe iPhone: lipsește $x"; fi
done
if [ "$upload" = "1" ]; then
  echo "::error::Nu încarc în TestFlight o versiune care cere notificări fără să le poată primi. Adăugați ce lipsește (docs/release.md, Ce mai trebuie) sau rulați cu push_ios debifat și scoateți push-ul din note și din fișă."
  exit 1
fi
exit 0
