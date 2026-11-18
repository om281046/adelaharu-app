package ro.adelaharu.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ServiceInfo;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.os.SystemClock;
import android.support.v4.media.MediaMetadataCompat;
import android.support.v4.media.session.MediaSessionCompat;
import android.support.v4.media.session.PlaybackStateCompat;
import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.app.ServiceCompat;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * Serviciul de prim-plan (mediaPlayback) pentru meditații: MediaSession + notificarea media.
 * Ține aplicația vie cât cântă sunetul din WebView și arată titlul, poza și butoanele
 * (înapoi 15 s, play/pauză, înainte 15 s) pe ecranul blocat, în notificări și pe căști/ceas/mașină.
 * Pe pauză iese din prim-plan (notificarea se poate închide), la „opreste” dispare.
 */
public class AhMediaService extends Service {

    static final String CANAL = "meditatii";
    static final int ID_NOTIFICARE = 4711;
    static final String ACT = "ro.adelaharu.app.media.";
    static final String CA_INAPOI = "inapoi15";
    static final String CA_INAINTE = "inainte15";

    /** Starea trimisă din JS. */
    static class Stare {
        String titlu = "";
        String artist = "Adela Haru";
        String poza;
        double durata;
        double pozitie;
        boolean canta;
    }

    static volatile AhMediaService activ;
    /** Starea cu care pornește serviciul (pusă de plugin înainte de startForegroundService). */
    static volatile Stare deAplicat;

    private final Handler principal = new Handler(Looper.getMainLooper());
    private final ExecutorService fundal = Executors.newSingleThreadExecutor();
    private MediaSessionCompat sesiune;
    private Stare stare = new Stare();
    private String pozaIncarcata;
    private Bitmap poza;
    private boolean inPrimPlan;

    @Override
    public void onCreate() {
        super.onCreate();
        activ = this;
        canal();
        sesiune = new MediaSessionCompat(this, "AdelaHaru");
        sesiune.setCallback(
            new MediaSessionCompat.Callback() {
                @Override
                public void onPlay() {
                    AhMediaPlugin.trimite("play", null);
                }

                @Override
                public void onPause() {
                    AhMediaPlugin.trimite("pause", null);
                }

                @Override
                public void onStop() {
                    AhMediaPlugin.trimite("stop", null);
                }

                @Override
                public void onFastForward() {
                    AhMediaPlugin.trimite("inainte", null);
                }

                @Override
                public void onRewind() {
                    AhMediaPlugin.trimite("inapoi", null);
                }

                @Override
                public void onSkipToNext() {
                    AhMediaPlugin.trimite("inainte", null);
                }

                @Override
                public void onSkipToPrevious() {
                    AhMediaPlugin.trimite("inapoi", null);
                }

                @Override
                public void onSeekTo(long pos) {
                    AhMediaPlugin.trimite("seek", pos / 1000.0);
                }

                @Override
                public void onCustomAction(String action, Bundle extras) {
                    if (CA_INAPOI.equals(action)) AhMediaPlugin.trimite("inapoi", null);
                    else if (CA_INAINTE.equals(action)) AhMediaPlugin.trimite("inainte", null);
                }
            }
        );
        Intent deschide = getPackageManager().getLaunchIntentForPackage(getPackageName());
        if (deschide != null) {
            sesiune.setSessionActivity(PendingIntent.getActivity(this, 0, deschide, PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT));
        }
        sesiune.setActive(true);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        String a = intent != null ? intent.getAction() : null;
        if (a != null && a.startsWith(ACT)) {
            // apăsări din notificare (Android < 13 le arată ca butoane în notificare)
            String tip = a.substring(ACT.length());
            if ("comuta".equals(tip)) tip = stare.canta ? "pause" : "play";
            AhMediaPlugin.trimite(tip, null);
            if (!inPrimPlan && !stare.canta && "stop".equals(tip)) opreste();
            return START_NOT_STICKY;
        }
        Stare s = deAplicat;
        deAplicat = null;
        if (s == null) {
            s = stare;
            s.canta = true;
        }
        // startForegroundService cere startForeground în câteva secunde, deci imediat:
        aplica(s, true);
        return START_NOT_STICKY;
    }

    /** Apelat din plugin (orice fir). */
    void actualizeaza(Stare s) {
        principal.post(() -> aplica(s, false));
    }

    void opreste() {
        principal.post(() -> {
            try {
                sesiune.setActive(false);
            } catch (Exception ignored) {}
            ServiceCompat.stopForeground(this, ServiceCompat.STOP_FOREGROUND_REMOVE);
            inPrimPlan = false;
            NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (nm != null) nm.cancel(ID_NOTIFICARE);
            stopSelf();
        });
    }

    private void aplica(Stare s, boolean pornire) {
        stare = s;
        if (!sesiune.isActive()) sesiune.setActive(true);
        incarcaPoza(s.poza);

        MediaMetadataCompat.Builder m = new MediaMetadataCompat.Builder()
            .putString(MediaMetadataCompat.METADATA_KEY_TITLE, s.titlu)
            .putString(MediaMetadataCompat.METADATA_KEY_ARTIST, s.artist)
            .putString(MediaMetadataCompat.METADATA_KEY_ALBUM, "Inner Temple")
            .putLong(MediaMetadataCompat.METADATA_KEY_DURATION, s.durata > 0 ? (long) (s.durata * 1000) : -1);
        if (poza != null) m.putBitmap(MediaMetadataCompat.METADATA_KEY_ALBUM_ART, poza);
        sesiune.setMetadata(m.build());

        long actiuni =
            PlaybackStateCompat.ACTION_PLAY |
            PlaybackStateCompat.ACTION_PAUSE |
            PlaybackStateCompat.ACTION_PLAY_PAUSE |
            PlaybackStateCompat.ACTION_STOP |
            PlaybackStateCompat.ACTION_SEEK_TO |
            PlaybackStateCompat.ACTION_FAST_FORWARD |
            PlaybackStateCompat.ACTION_REWIND;
        // fără SKIP_TO_PREVIOUS/NEXT: Android 13+ pune în locul lor acțiunile noastre de ±15 s
        sesiune.setPlaybackState(
            new PlaybackStateCompat.Builder()
                .setActions(actiuni)
                .addCustomAction(new PlaybackStateCompat.CustomAction.Builder(CA_INAPOI, "Înapoi 15 secunde", R.drawable.ic_ah_inapoi15).build())
                .addCustomAction(new PlaybackStateCompat.CustomAction.Builder(CA_INAINTE, "Înainte 15 secunde", R.drawable.ic_ah_inainte15).build())
                .setState(
                    s.canta ? PlaybackStateCompat.STATE_PLAYING : PlaybackStateCompat.STATE_PAUSED,
                    (long) (Math.max(0, s.pozitie) * 1000),
                    s.canta ? 1f : 0f,
                    SystemClock.elapsedRealtime()
                )
                .build()
        );

        Notification n = notificare(s);
        if (s.canta || pornire) {
            int tip = Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q ? ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK : 0;
            try {
                ServiceCompat.startForeground(this, ID_NOTIFICARE, n, tip);
                inPrimPlan = true;
            } catch (Exception e) {
                // Android 12+ refuză prim-planul pornit din fundal; rămâne notificarea simplă
                notifica(n);
            }
            if (!s.canta) {
                ServiceCompat.stopForeground(this, ServiceCompat.STOP_FOREGROUND_DETACH);
                inPrimPlan = false;
            }
        } else {
            // pauză: notificarea rămâne, dar se poate închide; Android poate opri serviciul liniștit
            if (inPrimPlan) {
                ServiceCompat.stopForeground(this, ServiceCompat.STOP_FOREGROUND_DETACH);
                inPrimPlan = false;
            }
            notifica(n);
        }
    }

    private void notifica(Notification n) {
        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm != null) {
            try {
                nm.notify(ID_NOTIFICARE, n);
            } catch (SecurityException ignored) {
                // fără permisiunea de notificări: rămân comenzile din MediaSession
            }
        }
    }

    private PendingIntent actiune(String tip, int cod) {
        Intent i = new Intent(this, AhMediaService.class).setAction(ACT + tip);
        return PendingIntent.getService(this, cod, i, PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
    }

    private Notification notificare(Stare s) {
        NotificationCompat.Builder b = new NotificationCompat.Builder(this, CANAL)
            .setSmallIcon(R.drawable.ic_stat_lotus)
            .setContentTitle(s.titlu)
            .setContentText(s.artist)
            .setLargeIcon(poza)
            .setColor(0xFF964E46)
            .setOnlyAlertOnce(true)
            .setShowWhen(false)
            .setOngoing(s.canta)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setCategory(NotificationCompat.CATEGORY_TRANSPORT)
            .setContentIntent(sesiune.getController().getSessionActivity())
            .setDeleteIntent(actiune("stop", 4))
            .addAction(R.drawable.ic_ah_inapoi15, "Înapoi 15 secunde", actiune("inapoi", 1))
            .addAction(
                s.canta ? R.drawable.ic_ah_pauza : R.drawable.ic_ah_play,
                s.canta ? "Pauză" : "Ascultă",
                actiune("comuta", 2)
            )
            .addAction(R.drawable.ic_ah_inainte15, "Înainte 15 secunde", actiune("inainte", 3))
            .setStyle(new androidx.media.app.NotificationCompat.MediaStyle().setMediaSession(sesiune.getSessionToken()).setShowActionsInCompactView(0, 1, 2));
        return b.build();
    }

    private void canal() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        NotificationManager nm = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm == null || nm.getNotificationChannel(CANAL) != null) return;
        NotificationChannel c = new NotificationChannel(CANAL, "Meditații", NotificationManager.IMPORTANCE_LOW);
        c.setDescription("Meditația care se aude acum, cu butoanele de redare.");
        c.setShowBadge(false);
        nm.createNotificationChannel(c);
    }

    /** Poza: file://… se citește pe loc (e mică), https://… în fundal, apoi se reface notificarea. */
    private void incarcaPoza(@Nullable String u) {
        if (u == null || u.isEmpty()) {
            poza = null;
            pozaIncarcata = null;
            return;
        }
        if (u.equals(pozaIncarcata)) return;
        pozaIncarcata = u;
        poza = null;
        if (u.startsWith("file://")) {
            poza = micsoreaza(BitmapFactory.decodeFile(Uri.parse(u).getPath()));
            return;
        }
        if (!u.startsWith("https://")) return;
        fundal.execute(() -> {
            Bitmap b = null;
            HttpURLConnection c = null;
            try {
                c = (HttpURLConnection) new URL(u).openConnection();
                c.setConnectTimeout(8000);
                c.setReadTimeout(8000);
                try (InputStream in = c.getInputStream()) {
                    b = micsoreaza(BitmapFactory.decodeStream(in));
                }
            } catch (Exception ignored) {} finally {
                if (c != null) c.disconnect();
            }
            final Bitmap gata = b;
            principal.post(() -> {
                if (gata != null && u.equals(pozaIncarcata)) {
                    poza = gata;
                    aplica(stare, false);
                }
            });
        });
    }

    @Nullable
    private static Bitmap micsoreaza(@Nullable Bitmap b) {
        if (b == null) return null;
        int max = 512;
        int w = b.getWidth(), h = b.getHeight();
        if (w <= max && h <= max) return b;
        float k = Math.min((float) max / w, (float) max / h);
        return Bitmap.createScaledBitmap(b, Math.round(w * k), Math.round(h * k), true);
    }

    @Override
    public void onTaskRemoved(Intent rootIntent) {
        // aplicația a fost închisă din lista de aplicații: sunetul din WebView s-a oprit și el
        opreste();
        super.onTaskRemoved(rootIntent);
    }

    @Override
    public void onDestroy() {
        if (activ == this) activ = null;
        fundal.shutdownNow();
        sesiune.release();
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
