import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'ro.adelaharu.app',
  appName: 'Adela Haru',
  webDir: 'dist',
  backgroundColor: '#FFF7F0',
  ios: {
    contentInset: 'never',
    backgroundColor: '#FFF7F0',
    // WKWebView: lăsăm sunetul să meargă cu ecranul blocat (împreună cu UIBackgroundModes audio)
    allowsLinkPreview: false,
  },
  android: {
    backgroundColor: '#FFF7F0',
  },
  plugins: {
    CapacitorHttp: { enabled: true },
    LocalNotifications: { iconColor: '#A8573A' },
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
  },
};

export default config;
