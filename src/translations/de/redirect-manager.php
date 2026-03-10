<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 * @since     5.0.0
 */

return [
    'Redirect Manager' => 'Redirect Manager',
    '{name} plugin loaded' => '{name} plugin loaded',
    '{pluginName} Cache' => '{pluginName} Cache',

    // =========================================================================
    // Navigation & Settings Sidebar
    // =========================================================================

    'Dashboard' => 'Dashboard',
    'Redirects' => 'Weiterleitungen',
    'Analytics' => 'Analytik',
    'Settings' => 'Einstellungen',
    'Logs' => 'Protokolle',
    'General' => 'Allgemein',
    'Interface' => 'Oberfläche',
    'Backup' => 'Backup',
    'Cache' => 'Cache',
    'Advanced' => 'Erweitert',
    'Test' => 'Test',

    // =========================================================================
    // Permissions
    // =========================================================================

    'View redirects' => 'Weiterleitungen anzeigen',
    'Create redirects' => 'Weiterleitungen erstellen',
    'Edit redirects' => 'Weiterleitungen bearbeiten',
    'Delete redirects' => 'Weiterleitungen löschen',
    'View analytics' => 'Analytik anzeigen',
    'View system logs' => 'Systemprotokolle anzeigen',
    'Manage settings' => 'Einstellungen verwalten',

    // =========================================================================
    // Common / Shared
    // =========================================================================

    'Save Settings' => 'Einstellungen speichern',
    'All' => 'Alle',
    'All Types' => 'Alle Typen',
    'All Sites' => 'Alle Websites',
    'Yes' => 'Ja',
    'No' => 'Nein',
    'Site' => 'Website',
    'URL' => 'URL',
    'Hits' => 'Zugriffe',
    'Status' => 'Status',
    'Type' => 'Typ',
    'Enabled' => 'Aktiviert',
    'Disabled' => 'Deaktiviert',
    'Handled' => 'Behandelt',
    'Unhandled' => 'Unbehandelt',
    'Count' => 'Anzahl',
    'Referrer' => 'Referrer',
    'Source URL' => 'Quell-URL',
    'Destination URL' => 'Ziel-URL',
    'Match Type' => 'Abgleichtyp',
    'Status Code' => 'Statuscode',
    'Priority' => 'Priorität',
    'Hit Count' => 'Zugriffe',
    'Last Hit' => 'Letzter Zugriff',
    'Created' => 'Erstellt',
    'Updated' => 'Aktualisiert',
    'Learn more' => 'Mehr erfahren',
    'Enable' => 'Aktivieren',
    'Disable' => 'Deaktivieren',
    'Import' => 'Importieren',
    'Cancel' => 'Abbrechen',

    // Match Types
    'Exact Match' => 'Exakter Abgleich',
    'RegEx Match' => 'RegEx-Abgleich',
    'Wildcard Match' => 'Platzhalter-Abgleich',
    'Prefix Match' => 'Präfix-Abgleich',
    'Match' => 'Abgleich',

    // =========================================================================
    // Dashboard (404 Analytics Table)
    // =========================================================================

    'Search URLs...' => 'URLs suchen...',
    'Request Type' => 'Anfragetyp',
    'Normal' => 'Normal',
    'Bot' => 'Bot',
    'Security Probe' => 'Sicherheitsscan',
    'Probe' => 'Scan',
    'Device' => 'Gerät',
    'Browser' => 'Browser',
    'No analytics found.' => 'Keine Analysedaten gefunden.',
    'New {singularName}' => 'Neue {singularName}',
    'Visit URL' => 'URL besuchen',
    'Edit handling redirect' => 'Behandelnde Weiterleitung bearbeiten',
    'Security vulnerability scanning attempt' => 'Versuch eines Sicherheitslücken-Scans',
    'Regular browser request' => 'Reguläre Browser-Anfrage',
    'Edit {item}' => '{item} bearbeiten',
    'Create {item}' => '{item} erstellen',
    'Clear All' => 'Alle löschen',
    'Clear' => 'Löschen',
    'Are you sure you want to clear ALL analytics? This cannot be undone.' => 'Möchten Sie wirklich ALLE Analysedaten löschen? Diese Aktion kann nicht rückgängig gemacht werden.',

    // =========================================================================
    // Redirects Listing
    // =========================================================================

    'Creation Type' => 'Erstellungstyp',
    'Manual' => 'Manuell',
    'Auto-created' => 'Automatisch erstellt',
    'Auto' => 'Auto',
    'Search {pluginName}...' => '{pluginName} durchsuchen...',
    'No {items} found.' => 'Keine {items} gefunden.',
    'Source' => 'Quelle',
    'Entry Changes' => 'Eintragsänderungen',
    'User' => 'Benutzer',

    // =========================================================================
    // Redirect Edit Page
    // =========================================================================

    'Edit {singularName}' => '{singularName} bearbeiten',
    'Source Match Mode' => 'Quell-Abgleichmodus',
    'Match by complete URL including domain (e.g., https://example.com/old-page).' => 'Abgleich anhand der vollständigen URL einschließlich Domain (z.B. https://example.com/alte-seite).',
    'Match by path only (e.g., /old-page). Works across all domains.' => 'Abgleich nur anhand des Pfads (z.B. /alte-seite). Funktioniert domainübergreifend.',
    'Path Only' => 'Nur Pfad',
    'Full URL' => 'Vollständige URL',
    'Full URLs entered will be automatically converted to paths when saving.' => 'Eingegebene vollständige URLs werden beim Speichern automatisch in Pfade umgewandelt.',
    'How the source URL should be matched' => 'Wie die Quell-URL abgeglichen werden soll',
    'Enter the full URL to match (e.g., https://example.com/old-page).' => 'Vollständige URL zum Abgleichen eingeben (z.B. https://example.com/alte-seite).',
    'Enter the path to match (e.g., /old-page). Full URLs will be automatically converted to paths.' => 'Pfad zum Abgleichen eingeben (z.B. /alte-seite). Vollständige URLs werden automatisch in Pfade umgewandelt.',
    'Test your pattern at' => 'Testen Sie Ihr Muster unter',
    'before saving.' => 'bevor Sie speichern.',
    'Full URL (https://example.com) or path (/page)' => 'Vollständige URL (https://example.com) oder Pfad (/seite)',
    'Redirects are checked in priority order (0 = highest priority, 9 = lowest). Use this when you have overlapping patterns. For example, set a specific pattern to priority 0 and a general catch-all to priority 9.' => 'Weiterleitungen werden in der Prioritätsreihenfolge geprüft (0 = höchste Priorität, 9 = niedrigste). Verwenden Sie dies bei überlappenden Mustern. Setzen Sie beispielsweise ein spezifisches Muster auf Priorität 0 und ein allgemeines Auffangmuster auf Priorität 9.',
    'Highest priority' => 'Höchste Priorität',
    'Lowest priority' => 'Niedrigste Priorität',
    'The HTTP status code to use for the redirect' => 'Der HTTP-Statuscode für die Weiterleitung',
    'Most common: Use' => 'Am häufigsten: Verwenden Sie',
    'for permanent moves.' => 'für dauerhafte Weiterleitungen.',
    'Learn more about HTTP status codes' => 'Mehr über HTTP-Statuscodes erfahren',
    '301 - Moved Permanently' => '301 – Dauerhaft verschoben',
    '302 - Found (Temporary)' => '302 – Gefunden (Temporär)',
    '303 - See Other' => '303 – Siehe andere',
    '307 - Temporary Redirect' => '307 – Temporäre Weiterleitung',
    '308 - Permanent Redirect' => '308 – Permanente Weiterleitung',
    '410 - Gone' => '410 – Entfernt',
    'Are you sure you want to delete this {item}?' => 'Möchten Sie {item} wirklich löschen?',
    'Live' => 'Live',
    'Hit count' => 'Zugriffe',
    'Last hit' => 'Letzter Zugriff',

    // Redirect edit: source match mode instructions (JS)
    'Match by path pattern (regex). Works across all domains.' => 'Abgleich anhand eines Pfadmusters (RegEx). Funktioniert domainübergreifend.',
    'Match by full URL pattern (regex) including domain.' => 'Abgleich anhand eines vollständigen URL-Musters (RegEx) einschließlich Domain.',
    'Enter a regex pattern to match paths (e.g., ^/blog/.* or /category/[^/]+).' => 'RegEx-Muster zum Abgleichen von Pfaden eingeben (z.B. ^/blog/.* oder /category/[^/]+).',
    'Enter a regex pattern to match full URLs (e.g., ^https://example.com/blog/.*).' => 'RegEx-Muster zum Abgleichen vollständiger URLs eingeben (z.B. ^https://example.com/blog/.*).',
    'Match by path pattern (wildcard). Works across all domains.' => 'Abgleich anhand eines Pfadmusters (Platzhalter). Funktioniert domainübergreifend.',
    'Match by full URL pattern (wildcard) including domain.' => 'Abgleich anhand eines vollständigen URL-Musters (Platzhalter) einschließlich Domain.',
    'Enter a wildcard pattern to match paths. Use * for any characters (e.g., /blog/* or /category/*/posts).' => 'Platzhaltermuster zum Abgleichen von Pfaden eingeben. Verwenden Sie * für beliebige Zeichen (z.B. /blog/* oder /category/*/posts).',
    'Enter a wildcard pattern to match full URLs. Use * for any characters (e.g., https://example.com/blog/*).' => 'Platzhaltermuster zum Abgleichen vollständiger URLs eingeben. Verwenden Sie * für beliebige Zeichen (z.B. https://example.com/blog/*).',
    'Match any path starting with the pattern. Works across all domains.' => 'Beliebigen Pfad abgleichen, der mit dem Muster beginnt. Funktioniert domainübergreifend.',
    'Match any URL starting with the pattern including domain.' => 'Beliebige URL abgleichen, die mit dem Muster beginnt, einschließlich Domain.',
    'Enter the starting path (e.g., /blog matches /blog, /blog/post, /blog/category).' => 'Startpfad eingeben (z.B. /blog stimmt überein mit /blog, /blog/post, /blog/category).',
    'Enter the starting URL (e.g., https://example.com/blog matches all URLs starting with it).' => 'Start-URL eingeben (z.B. https://example.com/blog stimmt mit allen URLs überein, die damit beginnen).',
    'Match any path containing the pattern. Works across all domains.' => 'Beliebigen Pfad abgleichen, der das Muster enthält. Funktioniert domainübergreifend.',
    'Match any URL containing the pattern including domain.' => 'Beliebige URL abgleichen, die das Muster enthält, einschließlich Domain.',
    'Enter text to match anywhere in the path (e.g., old-post matches /blog/old-post/123).' => 'Text eingeben, der irgendwo im Pfad vorkommt (z.B. alter-beitrag stimmt überein mit /blog/alter-beitrag/123).',
    'Enter text to match anywhere in the URL (e.g., old-post matches any URL containing it).' => 'Text eingeben, der irgendwo in der URL vorkommt (z.B. alter-beitrag stimmt mit jeder URL überein, die ihn enthält).',

    // =========================================================================
    // Redirect Analytics Panel (redirect edit → analytics tab)
    // =========================================================================

    'Total Hits' => 'Gesamte Zugriffe',
    'Human Visits' => 'Menschliche Besuche',
    'Bot Visits' => 'Bot-Besuche',
    'Top Referrers' => 'Häufigste Referrer',
    'Devices' => 'Geräte',
    'Browsers' => 'Browser',
    'Countries' => 'Länder',
    'Country' => 'Land',
    'No analytics data for this redirect yet.' => 'Noch keine Analysedaten für diese Weiterleitung vorhanden.',
    'Data will appear here once this redirect handles some requests.' => 'Daten erscheinen hier, sobald diese Weiterleitung einige Anfragen bearbeitet hat.',

    // =========================================================================
    // Analytics Page (404 Analytics)
    // =========================================================================

    '404 Not Found' => '404 Nicht gefunden',
    'Overview' => 'Übersicht',
    'Traffic & Devices' => 'Traffic & Geräte',
    'Geographic' => 'Geografisch',

    // Analytics: Overview tab
    'Total 404s' => 'Gesamte 404-Fehler',
    'Success Rate' => 'Erfolgsrate',
    '404 Trend' => '404-Trend',
    'Most Common 404s (Top 15)' => 'Häufigste 404-Fehler (Top 15)',
    'No 404s recorded yet' => 'Noch keine 404-Fehler aufgezeichnet',
    'Recent Unhandled 404s' => 'Aktuelle unbehandelte 404-Fehler',
    'Loading…' => 'Wird geladen…',

    // Analytics: Traffic & Devices tab
    'Traffic Analysis' => 'Traffic-Analyse',
    'Bot vs Human Traffic' => 'Bot- vs. menschlicher Traffic',
    'Top Bots' => 'Häufigste Bots',
    'Bot Name' => 'Bot-Name',
    '404s' => '404-Fehler',
    'Device Analytics' => 'Geräte-Analytik',
    'Device Types' => 'Gerätetypen',
    'Browser Usage' => 'Browser-Nutzung',
    'Operating Systems' => 'Betriebssysteme',

    // Analytics: Geographic tab
    'Geographic Analytics' => 'Geografische Analytik',
    'Top Countries' => 'Häufigste Länder',
    'Percentage' => 'Prozentsatz',
    'Top Cities' => 'Häufigste Städte',
    'City' => 'Stadt',
    'Geographic detection is disabled.' => 'Geografische Erkennung ist deaktiviert.',
    'Enable in Settings' => 'In den Einstellungen aktivieren',

    // Analytics: JS strings
    'No trend data available for the selected filters.' => 'Keine Trenddaten für die ausgewählten Filter verfügbar.',
    'No bot data available for the selected filters.' => 'Keine Bot-Daten für die ausgewählten Filter verfügbar.',
    'No device data available for the selected filters.' => 'Keine Gerätedaten für die ausgewählten Filter verfügbar.',
    'No browser data available for the selected filters.' => 'Keine Browser-Daten für die ausgewählten Filter verfügbar.',
    'No OS data available for the selected filters.' => 'Keine Betriebssystem-Daten für die ausgewählten Filter verfügbar.',
    'of traffic is from bots' => 'des Traffics stammt von Bots',
    'No unhandled 404s! Great job!' => 'Keine unbehandelten 404-Fehler! Ausgezeichnet!',
    'No bot data available' => 'Keine Bot-Daten verfügbar',
    'No country data available' => 'Keine Länderdaten verfügbar',
    'No city data available' => 'Keine Städtedaten verfügbar',
    'Create redirect' => 'Weiterleitung erstellen',

    // =========================================================================
    // Settings: General
    // =========================================================================

    'General Settings' => 'Allgemeine Einstellungen',
    'Plugin Name' => 'Plugin-Name',
    'The name of the plugin as it appears in the Control Panel menu' => 'Der Name des Plugins, wie er im Control-Panel-Menü erscheint',
    'This is being overridden by the <code>pluginName</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>pluginName</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Auto Create Redirects' => 'Weiterleitungen automatisch erstellen',
    'Automatically create redirects when entry URIs change' => 'Weiterleitungen automatisch erstellen, wenn sich Eintrags-URIs ändern',
    'This is being overridden by the <code>autoCreateRedirects</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>autoCreateRedirects</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Undo Window' => 'Rückgängig-Zeitfenster',
    'How long to detect and cancel immediate slug changes back and forth (A → B → A). Prevents creating unnecessary redirect pairs when editors quickly fix mistakes.' => 'Wie lange sofortige Slug-Änderungen hin und her erkannt und abgebrochen werden sollen (A → B → A). Verhindert das Erstellen unnötiger Weiterleitungspaare, wenn Redakteure Fehler schnell korrigieren.',
    'Disabled (always allow undo)' => 'Deaktiviert (Rückgängig immer erlauben)',
    '30 minutes' => '30 Minuten',
    '1 hour' => '1 Stunde',
    '2 hours' => '2 Stunden',
    '4 hours' => '4 Stunden',
    'This is being overridden by the <code>undoWindowMinutes</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>undoWindowMinutes</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Default Source Match Mode' => 'Standard-Quell-Abgleichmodus',
    'Default mode for new redirects. Each redirect can override this individually.' => 'Standardmodus für neue Weiterleitungen. Jede Weiterleitung kann dies individuell überschreiben.',
    'This is being overridden by the <code>redirectSrcMatch</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>redirectSrcMatch</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Query String Handling' => 'Query-String-Behandlung',
    'Strip Query String' => 'Query-String entfernen',
    'Strip query string from all 404 URLs before evaluation' => 'Query-String vor der Auswertung von allen 404-URLs entfernen',
    'This is being overridden by the <code>stripQueryString</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>stripQueryString</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Preserve Query String' => 'Query-String beibehalten',
    'Preserve and pass query string to destination URL' => 'Query-String beibehalten und an die Ziel-URL weitergeben',
    'This is being overridden by the <code>preserveQueryString</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>preserveQueryString</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'HTTP Headers' => 'HTTP-Header',
    'Set No-Cache Headers' => 'No-Cache-Header setzen',
    'Set no-cache headers on redirect responses' => 'No-Cache-Header bei Weiterleitungsantworten setzen',
    'To add additional custom headers, visit <a href="{url}">Advanced Settings</a>.' => 'Um weitere benutzerdefinierte Header hinzuzufügen, besuchen Sie die <a href="{url}">Erweiterten Einstellungen</a>.',
    'This is being overridden by the <code>setNoCacheHeaders</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>setNoCacheHeaders</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Logging Settings' => 'Protokollierungseinstellungen',
    'Log Level' => 'Protokollierungsstufe',
    'Choose what types of messages to log. Debug level requires devMode to be enabled.' => 'Wählen Sie, welche Arten von Meldungen protokolliert werden sollen. Die Debug-Stufe erfordert aktiviertes devMode.',
    'Error (Critical errors only)' => 'Fehler (Nur kritische Fehler)',
    'Warning (Errors and warnings)' => 'Warnung (Fehler und Warnungen)',
    'Info (General information)' => 'Info (Allgemeine Informationen)',
    'Debug (Detailed debugging)' => 'Debug (Detailliertes Debugging)',
    'This is being overridden by the <code>logLevel</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>logLevel</code> in <code>config/redirect-manager.php</code> überschrieben.',

    // =========================================================================
    // Settings: Analytics
    // =========================================================================

    'Analytics Settings' => 'Analytik-Einstellungen',
    'Enable Analytics' => 'Analytik aktivieren',
    'Track 404 analytics and visitor data' => '404-Analytik und Besucherdaten erfassen',
    'This is being overridden by the <code>enableAnalytics</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>enableAnalytics</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'When enabled, {pluginName} will track 404 errors including device types, browsers, geographic data, and bot traffic.' => 'Wenn aktiviert, erfasst {pluginName} 404-Fehler einschließlich Gerätetypen, Browser, geografische Daten und Bot-Traffic.',
    'Geographic Detection' => 'Geografische Erkennung',
    'Enable Geographic Detection' => 'Geografische Erkennung aktivieren',
    'Detect visitor location for analytics' => 'Besucherstandort für die Analytik erkennen',
    'This is being overridden by the <code>enableGeoDetection</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>enableGeoDetection</code> in <code>config/redirect-manager.php</code> überschrieben.',

    // Geo provider settings (from base partial)
    'Geo Provider' => 'Geo-Anbieter',
    'Select the geo IP lookup provider. HTTPS providers recommended for privacy.' => 'Geo-IP-Suchanbieter auswählen. HTTPS-Anbieter werden aus Datenschutzgründen empfohlen.',
    'ip-api.com (HTTP free, HTTPS paid)' => 'ip-api.com (HTTP kostenlos, HTTPS kostenpflichtig)',
    'ipapi.co (HTTPS, 1k/day free)' => 'ipapi.co (HTTPS, 1k/Tag kostenlos)',
    'ipinfo.io (HTTPS, 50k/month free)' => 'ipinfo.io (HTTPS, 50k/Monat kostenlos)',
    'This is being overridden by the <code>geoProvider</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>geoProvider</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'API Key' => 'API-Schlüssel',
    'Optional. Required for paid tiers (enables HTTPS for ip-api.com Pro).' => 'Optional. Erforderlich für kostenpflichtige Tarife (aktiviert HTTPS für ip-api.com Pro).',
    'This is being overridden by the <code>geoApiKey</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>geoApiKey</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'ip-api.com free tier uses HTTP. IP addresses will be transmitted unencrypted. Add an API key for HTTPS (Pro tier) or switch to ipapi.co/ipinfo.io.' => 'ip-api.com Free-Tarif verwendet HTTP. IP-Adressen werden unverschlüsselt übertragen. Fügen Sie einen API-Schlüssel für HTTPS (Pro-Tarif) hinzu oder wechseln Sie zu ipapi.co/ipinfo.io.',
    'ip-api.com: HTTP free tier (45 requests/min). Add API key for HTTPS (Pro tier, $13/month). IP addresses transmitted unencrypted without API key.' => 'ip-api.com: HTTP Free-Tarif (45 Anfragen/Min). API-Schlüssel für HTTPS hinzufügen (Pro-Tarif, 13 $/Monat). IP-Adressen werden ohne API-Schlüssel unverschlüsselt übertragen.',
    'ipapi.co: HTTPS with 1,000 free requests/day. API key optional (increases rate limits).' => 'ipapi.co: HTTPS mit 1.000 kostenlosen Anfragen/Tag. API-Schlüssel optional (erhöht Ratenlimits).',
    'ipinfo.io: HTTPS with 50,000 free requests/month. API key optional (increases rate limits).' => 'ipinfo.io: HTTPS mit 50.000 kostenlosen Anfragen/Monat. API-Schlüssel optional (erhöht Ratenlimits).',

    // IP salt error banner (from base partial)
    'error' => 'Fehler',
    'Configuration Required' => 'Konfiguration erforderlich',
    'IP hash salt is missing.' => 'IP-Hash-Salt fehlt.',
    'Analytics tracking requires a secure salt for privacy protection.' => 'Die Analytik-Verfolgung erfordert einen sicheren Salt zum Datenschutz.',
    'Run one of these commands in your terminal:' => 'Führen Sie einen der folgenden Befehle in Ihrem Terminal aus:',
    'Standard:' => 'Standard:',
    'COPY' => 'KOPIEREN',
    'DDEV:' => 'DDEV:',
    'This will automatically add' => 'Dadurch wird automatisch',
    'to your' => 'zu Ihrer',
    'file.' => 'Datei hinzugefügt.',
    'Warning:' => 'Warnung:',
    'Copy the same salt to staging and production environments.' => 'Kopieren Sie denselben Salt in Staging- und Produktionsumgebungen.',
    'COPIED!' => 'KOPIERT!',
    'Failed to copy to clipboard' => 'Kopieren in die Zwischenablage fehlgeschlagen',

    // Analytics: IP Privacy
    'IP Address Privacy' => 'IP-Adress-Datenschutz',
    'Anonymize IP Addresses' => 'IP-Adressen anonymisieren',
    'Mask IP addresses before storage for maximum privacy. <strong>IPv4</strong>: masks last octet (192.168.1.123 → 192.168.1.0). <strong>IPv6</strong>: masks last 80 bits. <strong>Trade-off</strong>: Reduces unique visitor accuracy (users on same subnet counted as one visitor). Geo-location still works normally.' => 'IP-Adressen vor der Speicherung für maximalen Datenschutz maskieren. <strong>IPv4</strong>: Letztes Oktett maskieren (192.168.1.123 → 192.168.1.0). <strong>IPv6</strong>: Letzte 80 Bits maskieren. <strong>Kompromiss</strong>: Reduziert die Genauigkeit eindeutiger Besucher (Benutzer im selben Subnetz werden als ein Besucher gezählt). Die Geolokalisierung funktioniert weiterhin normal.',
    'This is being overridden by the <code>anonymizeIpAddress</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>anonymizeIpAddress</code> in <code>config/redirect-manager.php</code> überschrieben.',

    // Analytics: Additional
    'Additional Settings' => 'Weitere Einstellungen',
    'Strip Query String From Stats' => 'Query-String aus Statistiken entfernen',
    'Strip query strings from analytics URLs to consolidate similar requests' => 'Query-Strings aus Analytik-URLs entfernen, um ähnliche Anfragen zusammenzufassen',
    'This is being overridden by the <code>stripQueryStringFromStats</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>stripQueryStringFromStats</code> in <code>config/redirect-manager.php</code> überschrieben.',

    // Analytics: Data Retention
    'Data Retention' => 'Datenspeicherung',
    'Analytics Retention (Days)' => 'Analytik-Aufbewahrungsdauer (Tage)',
    'Number of days to retain analytics (0 = keep forever)' => 'Anzahl der Tage, für die Analysedaten aufbewahrt werden (0 = für immer aufbewahren)',
    'This is being overridden by the <code>analyticsRetention</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>analyticsRetention</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Analytics Limit' => 'Analytik-Limit',
    'Maximum number of unique 404 records to retain' => 'Maximale Anzahl eindeutiger 404-Einträge, die aufbewahrt werden',
    'This is being overridden by the <code>analyticsLimit</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>analyticsLimit</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Auto Trim Analytics' => 'Analytik automatisch kürzen',
    'Automatically trim analytics to respect the limit' => 'Analysedaten automatisch kürzen, um das Limit einzuhalten',
    'This is being overridden by the <code>autoTrimAnalytics</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>autoTrimAnalytics</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Performance & Caching' => 'Performance & Caching',
    'Configure device detection and redirect caching for better performance.' => 'Geräteerkennung und Weiterleitungs-Caching für bessere Performance konfigurieren.',
    'Go to Cache Settings' => 'Zu den Cache-Einstellungen',

    // =========================================================================
    // Settings: Interface
    // =========================================================================

    'Interface Settings' => 'Oberflächen-Einstellungen',
    'Items Per Page' => 'Einträge pro Seite',
    'Number of items to display per page in lists' => 'Anzahl der Einträge, die pro Seite in Listen angezeigt werden',
    'This is being overridden by the <code>itemsPerPage</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>itemsPerPage</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Dashboard Refresh Interval' => 'Dashboard-Aktualisierungsintervall',
    'How often to refresh dashboard data. Set to Off to disable auto-refresh.' => 'Wie häufig Dashboard-Daten aktualisiert werden sollen. Auf "Aus" setzen, um die automatische Aktualisierung zu deaktivieren.',
    'Off' => 'Aus',
    '15 seconds' => '15 Sekunden',
    '30 seconds' => '30 Sekunden',
    '60 seconds (1 minute)' => '60 Sekunden (1 Minute)',
    '120 seconds (2 minutes)' => '120 Sekunden (2 Minuten)',
    'This is being overridden by the <code>refreshIntervalSecs</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>refreshIntervalSecs</code> in <code>config/redirect-manager.php</code> überschrieben.',

    // =========================================================================
    // Settings: Backup
    // =========================================================================

    'Backup Settings' => 'Backup-Einstellungen',
    'Enable Backups' => 'Backups aktivieren',
    'Enable automatic backup functionality for redirects' => 'Automatische Backup-Funktion für Weiterleitungen aktivieren',
    'This is being overridden by the <code>backupEnabled</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupEnabled</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Backup Before Import' => 'Backup vor dem Import',
    'Automatically create a backup before importing CSV files' => 'Vor dem Import von CSV-Dateien automatisch ein Backup erstellen',
    'This is being overridden by the <code>backupOnImport</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupOnImport</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Backup Schedule' => 'Backup-Zeitplan',
    "How often to create automatic backups. Uses Craft's queue if running, or set up a cron job:" => "Wie häufig automatische Backups erstellt werden sollen. Verwendet Crafts Queue, falls aktiv, oder richten Sie einen Cron-Job ein:",
    'Manual Only' => 'Nur manuell',
    'Daily' => 'Täglich',
    'Weekly' => 'Wöchentlich',
    'Monthly' => 'Monatlich',
    'This is being overridden by the <code>backupSchedule</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupSchedule</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Retention Period' => 'Aufbewahrungszeitraum',
    'Number of days to keep automatic backups (0 = keep forever). Manual backups are never deleted automatically.' => 'Anzahl der Tage, für die automatische Backups aufbewahrt werden (0 = für immer aufbewahren). Manuelle Backups werden niemals automatisch gelöscht.',
    'This is being overridden by the <code>backupRetentionDays</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupRetentionDays</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Backup Storage Volume' => 'Backup-Speicher-Volume',
    'Select an asset volume or use a custom path for storing backups.' => 'Wählen Sie ein Asset-Volume oder verwenden Sie einen benutzerdefinierten Pfad zum Speichern von Backups.',
    'Use custom path' => 'Benutzerdefinierten Pfad verwenden',
    'This is being overridden by the <code>backupVolumeUid</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupVolumeUid</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Custom Backup Path' => 'Benutzerdefinierter Backup-Pfad',
    'The custom path where backups should be stored (only used when no volume is selected)' => 'Der benutzerdefinierte Pfad, unter dem Backups gespeichert werden sollen (wird nur verwendet, wenn kein Volume ausgewählt ist)',
    'Use Craft path aliases: <code>@storage/redirect-manager/backups</code> (recommended) or <code>@root/backups/redirect-manager</code>. Paths must be outside webroot for security. Environment variables like <code>$ENV_VAR</code> are supported.' => 'Craft-Pfad-Aliase verwenden: <code>@storage/redirect-manager/backups</code> (empfohlen) oder <code>@root/backups/redirect-manager</code>. Pfade müssen sich aus Sicherheitsgründen außerhalb des Webroots befinden. Umgebungsvariablen wie <code>$ENV_VAR</code> werden unterstützt.',
    'This is being overridden by the <code>backupPath</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>backupPath</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Backup Location:' => 'Backup-Speicherort:',

    // =========================================================================
    // Settings: Cache
    // =========================================================================

    'Cache Settings' => 'Cache-Einstellungen',
    'Cache Storage Settings' => 'Cache-Speichereinstellungen',
    'Cache Storage Method' => 'Cache-Speichermethode',
    'How to store cache data. Use Redis/Database for load-balanced or multi-server environments.' => 'Wie Cache-Daten gespeichert werden sollen. Redis/Datenbank für lastverteilte oder Multi-Server-Umgebungen verwenden.',
    'File System (default, single server)' => 'Dateisystem (Standard, einzelner Server)',
    'Redis/Database (load-balanced, multi-server, cloud hosting)' => 'Redis/Datenbank (lastverteilt, Multi-Server, Cloud-Hosting)',
    'This is being overridden by the <code>cacheStorageMethod</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>cacheStorageMethod</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Cache Location' => 'Cache-Speicherort',
    "Using Craft's configured Redis cache from <code>config/app.php</code>" => "Es wird der in Craft konfigurierte Redis-Cache aus <code>config/app.php</code> verwendet",
    'Redis Not Configured' => 'Redis nicht konfiguriert',
    "To use Redis caching, install <code>yiisoft/yii2-redis</code> and configure it in <code>config/app.php</code>." => "Um Redis-Caching zu verwenden, installieren Sie <code>yiisoft/yii2-redis</code> und konfigurieren Sie es in <code>config/app.php</code>.",
    'Device Detection Caching' => 'Caching der Geräteerkennung',
    'Cache Device Detection' => 'Geräteerkennung cachen',
    'Cache device detection results for better performance' => 'Ergebnisse der Geräteerkennung für bessere Performance cachen',
    'This is being overridden by the <code>cacheDeviceDetection</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>cacheDeviceDetection</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Device Detection Cache Duration' => 'Cache-Dauer der Geräteerkennung',
    'Cache duration in seconds. Current:' => 'Cache-Dauer in Sekunden. Aktuell:',
    'Min: 60 (1 minute), Max: 604800 (7 days)' => 'Min: 60 (1 Minute), Max: 604800 (7 Tage)',
    'This is being overridden by the <code>deviceDetectionCacheDuration</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>deviceDetectionCacheDuration</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'How it works' => 'So funktioniert es',
    'Device detection parses user-agent strings to identify devices, browsers, and operating systems' => 'Die Geräteerkennung analysiert User-Agent-Strings, um Geräte, Browser und Betriebssysteme zu identifizieren',
    'Results are cached to avoid re-parsing the same user-agent repeatedly' => 'Ergebnisse werden gecacht, um das erneute Analysieren desselben User-Agents zu vermeiden',
    'Recommended to keep enabled for production sites' => 'Empfohlen, für Produktionsseiten aktiviert zu lassen',
    'Device detection caching is only available when Analytics is enabled. Go to' => 'Das Caching der Geräteerkennung ist nur verfügbar, wenn Analytik aktiviert ist. Gehen Sie zu',
    'to enable analytics.' => 'um Analytik zu aktivieren.',
    'Redirect Caching' => 'Weiterleitungs-Caching',
    'Enable Redirect Cache' => 'Weiterleitungs-Cache aktivieren',
    'Cache redirect lookups for improved performance. Recommended for production sites.' => 'Weiterleitungssuchen für bessere Performance cachen. Empfohlen für Produktionsseiten.',
    'This is being overridden by the <code>enableRedirectCache</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>enableRedirectCache</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Redirect Cache Duration' => 'Weiterleitungs-Cache-Dauer',
    'Min: 60 (1 minute), Max: 86400 (1 day)' => 'Min: 60 (1 Minute), Max: 86400 (1 Tag)',
    'This is being overridden by the <code>redirectCacheDuration</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>redirectCacheDuration</code> in <code>config/redirect-manager.php</code> überschrieben.',
    '{count} second' => '{count} Sekunde',
    '{count} seconds' => '{count} Sekunden',
    '{count} minute' => '{count} Minute',
    '{count} minutes' => '{count} Minuten',
    '{count} hour' => '{count} Stunde',
    '{count} hours' => '{count} Stunden',
    '{count} day' => '{count} Tag',
    '{count} days' => '{count} Tage',

    // =========================================================================
    // Settings: Advanced
    // =========================================================================

    'Advanced Settings' => 'Erweiterte Einstellungen',
    'API Settings' => 'API-Einstellungen',
    'Enable API Endpoint' => 'API-Endpunkt aktivieren',
    'Enable GraphQL endpoint for headless implementations<br><strong>Note:</strong> GraphQL API coming soon in future update.' => 'GraphQL-Endpunkt für Headless-Implementierungen aktivieren<br><strong>Hinweis:</strong> GraphQL API kommt bald in einem zukünftigen Update.',
    'Coming soon - GraphQL API is planned for a future release' => 'Demnächst verfügbar – GraphQL API ist für eine zukünftige Version geplant',
    'This is being overridden by the <code>enableApiEndpoint</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>enableApiEndpoint</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'URL Filtering' => 'URL-Filterung',
    'Exclude Patterns' => 'Ausschlussmuster',
    '[Regular expressions](https://regexr.com/) to match URIs that should be excluded from {pluginName}.' => '[Reguläre Ausdrücke](https://regexr.com/) zum Abgleichen von URIs, die von {pluginName} ausgeschlossen werden sollen.',
    'RegEx pattern to exclude' => 'Auszuschließendes RegEx-Muster',
    'This is being overridden by the <code>excludePatterns</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>excludePatterns</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Additional Headers' => 'Zusätzliche Header',
    'Additional headers to add to the redirected request' => 'Zusätzliche Header, die der weitergeleiteten Anfrage hinzugefügt werden sollen',
    'Header Name' => 'Header-Name',
    'Header Value' => 'Header-Wert',
    'This is being overridden by the <code>additionalHeaders</code> setting in <code>config/redirect-manager.php</code>.' => 'Diese Einstellung wird durch <code>additionalHeaders</code> in <code>config/redirect-manager.php</code> überschrieben.',
    'Quick Setup' => 'Schnelleinrichtung',
    'Apply recommended exclude patterns and SEO headers.' => 'Empfohlene Ausschlussmuster und SEO-Header anwenden.',
    'Apply Recommended Settings' => 'Empfohlene Einstellungen anwenden',
    'Adds exclude patterns for Craft admin/CMS URLs, system resources (.well-known), versioned build assets (dist/assets), and SEO headers (X-Robots-Tag: noindex). Safe for all installations.' => 'Fügt Ausschlussmuster für Craft-Admin/CMS-URLs, Systemressourcen (.well-known), versionierte Build-Assets (dist/assets) und SEO-Header (X-Robots-Tag: noindex) hinzu. Für alle Installationen geeignet.',
    'WordPress Migration' => 'WordPress-Migration',
    'Filter out WordPress bot traffic and spam 404s.' => 'WordPress-Bot-Traffic und Spam-404-Fehler herausfiltern.',
    'Apply WordPress Migration Filters' => 'WordPress-Migrationsfilter anwenden',
    'Adds exclude patterns for WordPress bot traffic (wp-includes, wp-json, wp-admin, wp-login, xmlrpc.php, feeds, ?p= permalinks, etc.). <strong>Note:</strong> /wp-content/uploads URLs are NOT excluded - those media files may need legitimate redirects.' => 'Fügt Ausschlussmuster für WordPress-Bot-Traffic hinzu (wp-includes, wp-json, wp-admin, wp-login, xmlrpc.php, Feeds, ?p=-Permalinks usw.). <strong>Hinweis:</strong> /wp-content/uploads-URLs sind NICHT ausgeschlossen – diese Mediendateien benötigen möglicherweise legitime Weiterleitungen.',
    'Security Probe Filters' => 'Sicherheitsscan-Filter',
    'Filter out malicious vulnerability scanning attempts.' => 'Bösartige Versuche zum Scannen von Sicherheitslücken herausfiltern.',
    'Apply Security Probe Filters' => 'Sicherheitsscan-Filter anwenden',
    'Adds specific exclude patterns for security probes: database dumps (*.sql, dump.sql.gz), config files (.env, .git/, .htaccess), admin panels (/phpmyadmin, /pma/, adminer.php), and exploit attempts (shell.php, /cgi-bin/). Patterns are precise to avoid blocking legitimate URLs like /mysql-tips or /debugging-guide.' => 'Fügt spezifische Ausschlussmuster für Sicherheitsscans hinzu: Datenbank-Dumps (*.sql, dump.sql.gz), Konfigurationsdateien (.env, .git/, .htaccess), Admin-Panels (/phpmyadmin, /pma/, adminer.php) und Exploit-Versuche (shell.php, /cgi-bin/). Muster sind präzise, um legitime URLs wie /mysql-tips oder /debugging-guide nicht zu blockieren.',

    // =========================================================================
    // Settings: Test
    // =========================================================================

    'Test Redirects' => 'Weiterleitungen testen',
    'Test URL Redirects' => 'URL-Weiterleitungen testen',
    'Test if a URL matches any of your configured redirects without actually visiting it. Useful for validating Source Match Mode (path vs full URL) and Match Type logic.' => 'Testen Sie, ob eine URL mit einer Ihrer konfigurierten Weiterleitungen übereinstimmt, ohne sie tatsächlich aufzurufen. Nützlich zur Überprüfung des Quell-Abgleichmodus (Pfad vs. vollständige URL) und der Abgleichtyp-Logik.',
    'Test URL' => 'Test-URL',
    'Enter a URL to test (can be a full URL like https://example.com/old-page or a path like /old-page)' => 'Zu testende URL eingeben (kann eine vollständige URL wie https://example.com/alte-seite oder ein Pfad wie /alte-seite sein)',

    // =========================================================================
    // Backups Page
    // =========================================================================

    'Backups' => 'Backups',
    'Create Backup Now' => 'Jetzt Backup erstellen',
    'Backups are automatically created when you import redirects (if enabled). You can restore or download any backup.' => 'Backups werden automatisch erstellt, wenn Sie Weiterleitungen importieren (sofern aktiviert). Sie können jedes Backup wiederherstellen oder herunterladen.',
    'No backup history yet. Backups are created automatically when you import redirects.' => 'Noch keine Backup-Historie vorhanden. Backups werden automatisch erstellt, wenn Sie Weiterleitungen importieren.',
    'Date' => 'Datum',
    'Created By' => 'Erstellt von',
    'Redirect Count' => 'Anzahl Weiterleitungen',
    'Size' => 'Größe',
    'Actions' => 'Aktionen',
    'Failed to load backups: ' => 'Backups konnten nicht geladen werden: ',
    'Backup created.' => 'Backup erstellt.',
    'Failed to create backup.' => 'Backup konnte nicht erstellt werden.',
    'Are you sure you want to restore this backup? This will replace all current redirects. A backup of the current state will be created before restoring.' => 'Möchten Sie dieses Backup wirklich wiederherstellen? Dadurch werden alle aktuellen Weiterleitungen ersetzt. Vor der Wiederherstellung wird ein Backup des aktuellen Zustands erstellt.',
    'Backup contains' => 'Backup enthält',
    'redirects.' => 'Weiterleitungen.',
    'Restoring backup...' => 'Backup wird wiederhergestellt...',
    'Backup restored.' => 'Backup wiederhergestellt.',
    'Failed to restore backup.' => 'Backup konnte nicht wiederhergestellt werden.',
    'Are you sure you want to delete this backup? This action cannot be undone.' => 'Möchten Sie dieses Backup wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.',
    'Deleting backup...' => 'Backup wird gelöscht...',
    'Backup deleted.' => 'Backup gelöscht.',
    'Failed to delete backup.' => 'Backup konnte nicht gelöscht werden.',

    // =========================================================================
    // Import/Export
    // =========================================================================

    'Import/Export' => 'Import/Export',
    'Import History' => 'Import-Verlauf',
    'Export Redirects' => 'Weiterleitungen exportieren',
    'Download all your current redirects as a CSV file for backup or migration to another site.' => 'Laden Sie alle aktuellen Weiterleitungen als CSV-Datei für Backup oder Migration zu einer anderen Website herunter.',
    'Export All Redirects as CSV' => 'Alle Weiterleitungen als CSV exportieren',
    'You do not have permission to export redirects.' => 'Sie haben keine Berechtigung, Weiterleitungen zu exportieren.',
    'Import Redirects' => 'Weiterleitungen importieren',
    'CSV Format' => 'CSV-Format',
    'Required columns:' => 'Erforderliche Spalten:',
    'sourceUrl' => 'sourceUrl',
    'The URL to redirect from' => 'Die URL, von der weitergeleitet werden soll',
    'destinationUrl' => 'destinationUrl',
    'The URL to redirect to' => 'Die URL, zu der weitergeleitet werden soll',
    'Optional columns:' => 'Optionale Spalten:',
    'statusCode' => 'statusCode',
    'HTTP status (301, 302, etc.)' => 'HTTP-Status (301, 302 usw.)',
    'matchType' => 'matchType',
    'exact, regex, wildcard, or prefix' => 'exact, regex, wildcard oder prefix',
    'redirectSrcMatch' => 'redirectSrcMatch',
    'pathonly or fullurl (default: pathonly)' => 'pathonly oder fullurl (Standard: pathonly)',
    'siteId' => 'siteId',
    'Site ID (blank = all sites)' => 'Website-ID (leer = alle Websites)',
    'priority' => 'priority',
    '0-9 (default: 0)' => '0-9 (Standard: 0)',
    'enabled' => 'enabled',
    '1 or 0 (default: 1)' => '1 oder 0 (Standard: 1)',
    'Example:' => 'Beispiel:',
    'Import from CSV' => 'Aus CSV importieren',
    "Import redirects from a CSV file. You'll be able to map columns and preview before importing." => "Weiterleitungen aus einer CSV-Datei importieren. Sie können Spalten zuordnen und eine Vorschau anzeigen, bevor Sie importieren.",
    'CSV File' => 'CSV-Datei',
    'Select a CSV file to import redirects' => 'CSV-Datei auswählen, um Weiterleitungen zu importieren',
    'CSV Delimiter' => 'CSV-Trennzeichen',
    'Character used to separate values in your CSV (auto-detect is default)' => 'Zeichen zum Trennen von Werten in Ihrer CSV-Datei (automatische Erkennung ist Standard)',
    'Auto (detect)' => 'Auto (erkennen)',
    'Comma (,)' => 'Komma (,)',
    'Semicolon (;)' => 'Semikolon (;)',
    'Tab' => 'Tab',
    'Pipe (|)' => 'Pipe (|)',
    'Create Backup Before Import' => 'Backup vor dem Import erstellen',
    'Automatically backup existing redirects before importing (recommended)' => 'Vorhandene Weiterleitungen vor dem Import automatisch sichern (empfohlen)',
    'Backups are disabled in settings.' => 'Backups sind in den Einstellungen deaktiviert.',
    'The maximum file size is {size} and the import is limited to {rows} rows per file.' => 'Die maximale Dateigröße beträgt {size} und der Import ist auf {rows} Zeilen pro Datei begrenzt.',
    'Upload & Map Columns' => 'Hochladen & Spalten zuordnen',
    'You do not have permission to import redirects.' => 'Sie haben keine Berechtigung, Weiterleitungen zu importieren.',
    'Recent CSV imports and their results.' => 'Aktuelle CSV-Importe und ihre Ergebnisse.',
    'Clear history' => 'Verlauf löschen',
    'Filename' => 'Dateiname',
    'Imported' => 'Importiert',
    'Failed' => 'Fehlgeschlagen',
    'View' => 'Anzeigen',
    'No import history yet.' => 'Noch kein Import-Verlauf vorhanden.',
    'Are you sure you want to clear all import logs? This action cannot be undone.' => 'Möchten Sie wirklich alle Import-Protokolle löschen? Diese Aktion kann nicht rückgängig gemacht werden.',
    'Failed to clear history.' => 'Verlauf konnte nicht gelöscht werden.',

    // Import: Map columns
    'Map CSV Columns' => 'CSV-Spalten zuordnen',
    'Map Columns' => 'Spalten zuordnen',
    'Your CSV has {count} rows. Map each CSV column to a redirect field.' => 'Ihre CSV-Datei hat {count} Zeilen. Ordnen Sie jede CSV-Spalte einem Weiterleitungsfeld zu.',
    'Backup will be created automatically before importing to protect your existing redirects.' => 'Vor dem Import wird automatisch ein Backup erstellt, um Ihre vorhandenen Weiterleitungen zu schützen.',
    'Preview of CSV Data' => 'Vorschau der CSV-Daten',
    'Showing first 5 rows. {total} total rows will be imported.' => 'Die ersten 5 Zeilen werden angezeigt. Insgesamt {total} Zeilen werden importiert.',
    'Column Mapping' => 'Spaltenzuordnung',
    'Map your CSV columns to redirect fields. Required fields must be mapped.' => 'Ordnen Sie Ihre CSV-Spalten den Weiterleitungsfeldern zu. Pflichtfelder müssen zugeordnet werden.',
    '-- Do not import --' => '-- Nicht importieren --',
    'Source URL (required)' => 'Quell-URL (erforderlich)',
    'Destination URL (required)' => 'Ziel-URL (erforderlich)',
    'Site ID' => 'Website-ID',
    'Source Match Mode (pathonly/fullurl)' => 'Quell-Abgleichmodus (pathonly/fullurl)',
    'Match Type (exact/regex/wildcard/prefix)' => 'Abgleichtyp (exact/regex/wildcard/prefix)',
    'Status Code (301/302/etc.)' => 'Statuscode (301/302 usw.)',
    'Priority (0-9)' => 'Priorität (0-9)',
    'Enabled (1/0)' => 'Aktiviert (1/0)',
    'Last Hit (datetime)' => 'Letzter Zugriff (Datum/Uhrzeit)',
    'Creation Type (manual/auto/import)' => 'Erstellungstyp (manual/auto/import)',
    'Source Plugin' => 'Quell-Plugin',
    'Element ID (for auto-detection)' => 'Element-ID (für automatische Erkennung)',
    'CSV Column' => 'CSV-Spalte',
    'Maps to Field' => 'Zugeordnetes Feld',
    'Sample Data' => 'Beispieldaten',
    'Preview Import' => 'Import-Vorschau',

    // Import: Preview
    'Import Preview' => 'Import-Vorschau',
    'Preview' => 'Vorschau',
    'Total Rows' => 'Gesamte Zeilen',
    'Valid' => 'Gültig',
    'Duplicates' => 'Duplikate',
    'Errors' => 'Fehler',
    'Backup will be created with {count} existing redirect(s) before importing to protect your data.' => 'Vor dem Import wird ein Backup mit {count} vorhandenen Weiterleitungen erstellt, um Ihre Daten zu schützen.',
    'No existing redirects to backup - backup will be skipped.' => 'Keine vorhandenen Weiterleitungen zum Sichern – Backup wird übersprungen.',
    'Valid Redirects to Import' => 'Gültige Weiterleitungen zum Importieren',
    'Source Match' => 'Quell-Abgleich',
    'Duplicate Redirects (will be skipped)' => 'Doppelte Weiterleitungen (werden übersprungen)',
    'These redirects already exist with the same source URL, match type, and source match mode.' => 'Diese Weiterleitungen existieren bereits mit derselben Quell-URL, demselben Abgleichtyp und demselben Quell-Abgleichmodus.',
    'Reason' => 'Grund',
    'Invalid Rows (will be skipped)' => 'Ungültige Zeilen (werden übersprungen)',
    'Row' => 'Zeile',
    'Error' => 'Fehler',
    'Ready to Import' => 'Bereit zum Importieren',
    'Click the button below to import {count} valid redirect(s).' => 'Klicken Sie auf die Schaltfläche unten, um {count} gültige Weiterleitungen zu importieren.',
    '{duplicates} duplicate(s) will be skipped.' => '{duplicates} Duplikate werden übersprungen.',
    '{errors} invalid row(s) will be skipped.' => '{errors} ungültige Zeilen werden übersprungen.',
    'No valid redirects found to import.' => 'Keine gültigen Weiterleitungen zum Importieren gefunden.',
    'Import {count} Redirects' => '{count} Weiterleitungen importieren',
    'No Valid Redirects to Import' => 'Keine gültigen Weiterleitungen zum Importieren',
    'Successfully imported {imported} {pluginName}.' => '{imported} {pluginName} erfolgreich importiert.',

    // =========================================================================
    // Utilities Page
    // =========================================================================

    'All Active' => 'Alle aktiv',
    'Good' => 'Gut',
    'Check' => 'Prüfen',
    'Monitor 404 handling, manage redirects, and optimize cache performance.' => '404-Behandlung überwachen, Weiterleitungen verwalten und Cache-Performance optimieren.',
    'Redirects Status' => 'Weiterleitungsstatus',
    'Active {pluginName}' => 'Aktive {pluginName}',
    'Total' => 'Gesamt',
    'Active' => 'Aktiv',
    '404 Handling' => '404-Behandlung',
    'Success rate (last 7 days)' => 'Erfolgsrate (letzte 7 Tage)',
    'Cache Status' => 'Cache-Status',
    'Total cached entries' => 'Gesamte gecachte Einträge',
    'Manage Redirects' => 'Weiterleitungen verwalten',
    'View Analytics' => 'Analytik anzeigen',
    'View Settings' => 'Einstellungen anzeigen',
    'Navigation' => 'Navigation',
    'Access main plugin sections' => 'Auf die Hauptbereiche des Plugins zugreifen',
    'Clear Redirect Cache' => 'Weiterleitungs-Cache leeren',
    'Clear Device Cache' => 'Geräte-Cache leeren',
    'Clear All Caches' => 'Alle Caches leeren',
    'Cache Management' => 'Cache-Verwaltung',
    'Clear cached data to force regeneration. Useful when troubleshooting redirect issues.' => 'Gecachte Daten löschen, um eine Neugenerierung zu erzwingen. Nützlich bei der Fehlersuche bei Weiterleitungsproblemen.',
    'Analytics Data Management' => 'Verwaltung von Analysedaten',
    'Permanently delete all analytics tracking data. This action cannot be undone!' => 'Alle Analytik-Tracking-Daten dauerhaft löschen. Diese Aktion kann nicht rückgängig gemacht werden!',
    'Clear All Analytics' => 'Alle Analysedaten löschen',
    'Clear all caches?' => 'Alle Caches leeren?',
    'Are you sure you want to permanently delete ALL analytics data? This action cannot be undone!' => 'Möchten Sie wirklich ALLE Analysedaten dauerhaft löschen? Diese Aktion kann nicht rückgängig gemacht werden!',
    'This will delete all 404 tracking data. Are you absolutely sure?' => 'Dadurch werden alle 404-Tracking-Daten gelöscht. Sind Sie absolut sicher?',

    // =========================================================================
    // Widgets
    // =========================================================================

    // Stats Summary Widget
    'View full analytics' => 'Vollständige Analytik anzeigen',
    'No 404s recorded' => 'Keine 404-Fehler aufgezeichnet',
    '404 errors will appear here when they occur.' => '404-Fehler erscheinen hier, wenn sie auftreten.',
    'Number of Days' => 'Anzahl der Tage',
    'Show analytics for the last X days (1-365)' => 'Analytik für die letzten X Tage anzeigen (1-365)',

    // Unhandled 404s Widget
    'Last seen' => 'Zuletzt gesehen',
    'View all 404s' => 'Alle 404-Fehler anzeigen',
    'No unhandled 404s' => 'Keine unbehandelten 404-Fehler',
    'Great! All 404s are being handled by {pluginName}.' => 'Ausgezeichnet! Alle 404-Fehler werden von {pluginName} behandelt.',
    'Number of 404s' => 'Anzahl der 404-Fehler',
    'How many unhandled 404s to display (5-50)' => 'Wie viele unbehandelte 404-Fehler angezeigt werden sollen (5-50)',

    // =========================================================================
    // Messages (flash, notices, errors)
    // =========================================================================

    'Scheduled initial analytics cleanup job' => 'Initialen Analytik-Bereinigungsjob geplant',
    'Redirect saved successfully' => 'Weiterleitung erfolgreich gespeichert',
    'Redirect deleted successfully' => 'Weiterleitung erfolgreich gelöscht',
    'Analytics cleared successfully' => 'Analysedaten erfolgreich gelöscht',
];
