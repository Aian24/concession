package com.concession.app;

import androidx.appcompat.app.AppCompatActivity;
import android.annotation.SuppressLint;
import android.content.pm.ActivityInfo;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.content.Intent;

public class MainActivity extends AppCompatActivity {

    // ============================================================
    // IMPORTANT: Change this URL to your LIVE website URL before
    // building the final APK.
    // For local testing with Android Emulator, use: http://10.0.2.2/Concession/
    // For local testing with real device on same WiFi, use your PC's IP: http://192.168.x.x/Concession/
    // ============================================================
    private static final String WEB_URL = "https://rustylopez.com/Concession/";
    // ============================================================

    private WebView webView;
    private ProgressBar progressBar;
    private View splashView;

    private ValueCallback<Uri[]> fileChooserCallback;
    private static final int FILE_CHOOSER_REQUEST = 1001;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Remove title bar
        if (getSupportActionBar() != null) {
            getSupportActionBar().hide();
        }
        getWindow().setFlags(
            WindowManager.LayoutParams.FLAG_FULLSCREEN,
            WindowManager.LayoutParams.FLAG_FULLSCREEN
        );

        // Status bar color (purple theme)
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.LOLLIPOP) {
            getWindow().clearFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
            getWindow().addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
            getWindow().setStatusBarColor(0xFF0f172a);
            getWindow().setNavigationBarColor(0xFF0f172a);
        }

        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);
        splashView = findViewById(R.id.splashView);

        // WebView Settings
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setUserAgentString(settings.getUserAgentString() + " ConcessionApp/1.0");

        // Enable cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        // WebView Client - handle navigation
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                // Keep navigation within the app for our domain
                if (url.startsWith(WEB_URL) || url.contains("10.0.2.2") || url.contains("192.168.")) {
                    return false; // Let WebView handle it
                }
                // Open external links in browser
                Intent intent = new Intent(Intent.ACTION_VIEW, request.getUrl());
                startActivity(intent);
                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);
                // Hide splash after first page load
                if (splashView.getVisibility() == View.VISIBLE) {
                    splashView.animate()
                        .alpha(0f)
                        .setDuration(300)
                        .withEndAction(() -> splashView.setVisibility(View.GONE));
                }
            }
        });

        // WebChrome Client - handle file uploads (for avatar, pullout images, etc.)
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (fileChooserCallback != null) {
                    fileChooserCallback.onReceiveValue(null);
                }
                fileChooserCallback = callback;

                Intent intent = params.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST);
                } catch (Exception e) {
                    fileChooserCallback = null;
                    return false;
                }
                return true;
            }

            @Override
            public void onProgressChanged(WebView view, int progress) {
                progressBar.setProgress(progress);
            }
        });

        // Load the website
        webView.loadUrl(WEB_URL);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_REQUEST) {
            if (fileChooserCallback != null) {
                Uri[] results = null;
                if (resultCode == RESULT_OK && data != null) {
                    String dataString = data.getDataString();
                    if (dataString != null) {
                        results = new Uri[]{Uri.parse(dataString)};
                    }
                }
                fileChooserCallback.onReceiveValue(results);
                fileChooserCallback = null;
            }
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
