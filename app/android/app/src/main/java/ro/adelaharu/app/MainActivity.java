package ro.adelaharu.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

/**
 * Aplicația = Spațiul meu (server.url) + pluginurile din npm (se înregistrează singure)
 * + două pluginuri mici ale noastre: AhApp (ce poate telefonul) și AhMedia (ecranul blocat).
 */
public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(AhAppPlugin.class);
        registerPlugin(AhMediaPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
