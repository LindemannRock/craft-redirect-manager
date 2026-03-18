# Changelog

## [5.13.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.2...v5.13.0) (2026-03-18)


### Features

* add analytics count retrieval to RedirectManagerUtility ([c8204ca](https://github.com/LindemannRock/craft-redirect-manager/commit/c8204ca60264a8dcea96cce8a75bd15f734327c3))
* add analytics system, dashboard widgets, device detection, and IP privacy ([f428a1d](https://github.com/LindemannRock/craft-redirect-manager/commit/f428a1d8d350790a51b5acbce665d5f3bb324d38))
* add backup path and volume UID to settings table; remove import history table ([33eb038](https://github.com/LindemannRock/craft-redirect-manager/commit/33eb038d57d299fd714850a712f1ad0fb29addd1))
* add cache storage method configuration for different environments ([287fa40](https://github.com/LindemannRock/craft-redirect-manager/commit/287fa40364851c136068679d313fdefae82ccd92))
* add cache storage method configuration to settings table ([6b9e424](https://github.com/LindemannRock/craft-redirect-manager/commit/6b9e424c192355548251f65561343e7e9d575d89))
* add complete EN/DE translation ([d534845](https://github.com/LindemannRock/craft-redirect-manager/commit/d534845a9def0b94782208cde21824bd5c10f405))
* Add configurable geo IP provider settings with HTTPS support ([633cc5d](https://github.com/LindemannRock/craft-redirect-manager/commit/633cc5d8b937f798c98bf82f890b63358e5e811a))
* Add exportAnalytics permission and fix dashboard permissions ([843b4a1](https://github.com/LindemannRock/craft-redirect-manager/commit/843b4a1ad46eb227e08d0549f8574a949c136ed1))
* Add geographic analytics and traffic analysis tabs with detailed statistics ([1e986e3](https://github.com/LindemannRock/craft-redirect-manager/commit/1e986e33745b18b1fb1fe3575e71cc98f71a548d))
* add geographic analytics for top countries and cities ([de5b98f](https://github.com/LindemannRock/craft-redirect-manager/commit/de5b98f5c3ef7ef6a93b0c106895ad8e55c1da39))
* Add granular permissions and dynamic naming to redirect-manager ([60303af](https://github.com/LindemannRock/craft-redirect-manager/commit/60303af8c41b038a3922f753aa9f0cc7228b5a22))
* add PHPStan and EasyCodingStandard configurations; enhance code quality checks ([0727817](https://github.com/LindemannRock/craft-redirect-manager/commit/0727817203d8620d0830c4eecf8857584b1868ef))
* Add redirect analytics functionality with new template and service methods ([303faad](https://github.com/LindemannRock/craft-redirect-manager/commit/303faade49718b84d5c2c7221ef6aef527584bac))
* add regex capture group support ($1, $2, etc.) for redirect destinations ([56743b8](https://github.com/LindemannRock/craft-redirect-manager/commit/56743b890bd4608d9e1badf1094719343503197a))
* add setter method support and enhance config override checks for settings ([f1c6f1c](https://github.com/LindemannRock/craft-redirect-manager/commit/f1c6f1c730eb5b41ca229c4999c591cf49c585b8))
* add site options to redirect edit form and improve template structure ([c1acf3d](https://github.com/LindemannRock/craft-redirect-manager/commit/c1acf3dd56478451a970dde8d7c737f7ec43482c))
* add SVG icon for redirect manager ([33af8ee](https://github.com/LindemannRock/craft-redirect-manager/commit/33af8eee101beb1912c1e1b816591d34bb2ecf24))
* add tooltip for custom headers in no-cache settings ([f0bfcab](https://github.com/LindemannRock/craft-redirect-manager/commit/f0bfcabee48c02084171957d8b36260b61e6d9ce))
* add utility page with system monitoring and cache management ([6e78b12](https://github.com/LindemannRock/craft-redirect-manager/commit/6e78b12bbd3ff9fb9d3b6da4aff0abd520900e6b))
* **analytics:** enhance analytics data handling and visualization ([33973b4](https://github.com/LindemannRock/craft-redirect-manager/commit/33973b469c22dbdd81e19a6aaf0106191c08352a))
* **analytics:** enhance date range filtering and export functionality ([e15086f](https://github.com/LindemannRock/craft-redirect-manager/commit/e15086fa170af759b99dd1b91e1f0691ad19cefd))
* **analytics:** implement build process and update asset management ([36d19eb](https://github.com/LindemannRock/craft-redirect-manager/commit/36d19ebe4f6fc45820bdaa48821ef8bd2948d26b))
* **analytics:** streamline IP processing and remove redundancy ([88ba22c](https://github.com/LindemannRock/craft-redirect-manager/commit/88ba22c94b7978e011519caa455a8c423bdd78ad))
* **backups:** Implement backup functionality for redirects ([62a9fb1](https://github.com/LindemannRock/craft-redirect-manager/commit/62a9fb1ab8a95f904d47bab4cdc811256f600ff1))
* Centralize redirect integration with undo detection and notifications ([e9b3d95](https://github.com/LindemannRock/craft-redirect-manager/commit/e9b3d95814b8b0be27f333ea50234bbf87249c50))
* enhance analytics and redirects controllers with additional fields and improve dashboard UI ([90bad19](https://github.com/LindemannRock/craft-redirect-manager/commit/90bad19252f43b83b65a0147a43772a1b573d9ec))
* enhance analytics data handling and timezone conversion; improve dashboard pagination links ([8e72a18](https://github.com/LindemannRock/craft-redirect-manager/commit/8e72a189eeb5188a20de6e02b7f1304fb95d659d))
* enhance cache status display and button functionality based on storage method ([e2ed5dc](https://github.com/LindemannRock/craft-redirect-manager/commit/e2ed5dcec1f96f568ba72168acb43a0df66afef6))
* enhance dashboard and redirects UI with additional color coding for request and match types ([9c5bf9e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c5bf9e224351eeda533d725811b26359aec05e8))
* Enhance import/export functionality with improved URL parsing and auto-detection of creation type ([c5f8b20](https://github.com/LindemannRock/craft-redirect-manager/commit/c5f8b202ec5928353c04f51c0c5e0767034d326b))
* Enhance redirect matching to return all matches with resolved destinations ([53ca0da](https://github.com/LindemannRock/craft-redirect-manager/commit/53ca0da84cf8571253a43a9d8da8fd973a3ea5c7))
* enhance redirects listing page and improve dashboard settings ([51d56f1](https://github.com/LindemannRock/craft-redirect-manager/commit/51d56f1b2b051de6464b9c2e80b5247d08d38034))
* enhance templates to use dynamic plugin name for titles and labels ([2629559](https://github.com/LindemannRock/craft-redirect-manager/commit/2629559494a4f9ff2a893c43d44da8a554b0dc22))
* Enhance URL validation and error reporting in import/export process ([38f879c](https://github.com/LindemannRock/craft-redirect-manager/commit/38f879c424f23b22eafa06badd3bf8fe43d48fd2))
* fix nested permission pattern — remove viewRedirects ([4c34568](https://github.com/LindemannRock/craft-redirect-manager/commit/4c34568b4ff2de33e028d575ea5bbb6e44d16d44))
* implement AJAX-based dashboard data retrieval and auto-refresh functionality ([a64a83f](https://github.com/LindemannRock/craft-redirect-manager/commit/a64a83fae422fb6661aa630910494e6d0e64622a))
* implement cache storage method configuration and handling for Redis and file systems ([6b6058d](https://github.com/LindemannRock/craft-redirect-manager/commit/6b6058d5bd0f5f6fd37b978ebf77e8559bcdfd3c))
* implement logging improvements across controllers and services ([0f5b463](https://github.com/LindemannRock/craft-redirect-manager/commit/0f5b4632fb797325b29d74350bac44aead7ce68f))
* **import-export:** add clear import history functionality and view history permissions ([fa194ca](https://github.com/LindemannRock/craft-redirect-manager/commit/fa194ca007ec3ed3fff3602eaad5f87e27a86a9c))
* **import-export:** add import limits to controller and template ([35b7519](https://github.com/LindemannRock/craft-redirect-manager/commit/35b75192f28fa7a795165b1e381bbc49e1e6da71))
* **info-box:** add new Info Box component for displaying informational notices ([353777e](https://github.com/LindemannRock/craft-redirect-manager/commit/353777e962289b40a090e6d0ec1720e46f99b7ce))
* initial Redirect Manager plugin implementation ([153c2ab](https://github.com/LindemannRock/craft-redirect-manager/commit/153c2aba744c196d8b7ea445c329bb87b179b664))
* **license:** add MIT License file to the repository ([649ff9e](https://github.com/LindemannRock/craft-redirect-manager/commit/649ff9e28aae8d3310d670653202688c26fc8c8d))
* migrate to lindemannrock/craft-plugin-base ([cf63978](https://github.com/LindemannRock/craft-redirect-manager/commit/cf6397815862f5c48746dc6fa6dbfb22ce9d2bbd))
* **plugin:** enhance plugin name handling and introduce Twig extension for name variations ([a73917f](https://github.com/LindemannRock/craft-redirect-manager/commit/a73917f31972adc31f34546338ce8049e3492c98))
* **redirect-manager:** add install experience configuration ([9c4344e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c4344ef16aaa8ada251457d6dc4904b1ef83367))
* **redirect-manager:** dashboard UI improvements and bug fixes ([dec1968](https://github.com/LindemannRock/craft-redirect-manager/commit/dec196864b9152c68873030a20f1e6cb9b56d8bc))
* Refactor backup and error messaging components in import/export templates ([2a7c903](https://github.com/LindemannRock/craft-redirect-manager/commit/2a7c9038068dcdcb6ff081b145eaee174172285a))
* replace direct plugin access with PluginHelper methods ([9ec3fc5](https://github.com/LindemannRock/craft-redirect-manager/commit/9ec3fc543d82711d5447a714951a412aad796c09))
* **settings:** add default country and city for local development ([ad34d13](https://github.com/LindemannRock/craft-redirect-manager/commit/ad34d1331b29bb8059759c234567a706d4395c55))
* **settings:** add WordPress migration filters and update settings description ([ce424e1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce424e193c89e73627feaa287a7dd38416b5a4d4))
* **settings:** allow disabling undo window and improve site handling in redirects ([53903de](https://github.com/LindemannRock/craft-redirect-manager/commit/53903de93e8c8a7d2007a0e960024104b1b4a40d))
* Simplify user permissions by grouping redirect management actions ([9230f3a](https://github.com/LindemannRock/craft-redirect-manager/commit/9230f3aa89e4023c8b4982e17a1c7625372e80c6))
* Update backup path structure to include 'imports' subdirectory for better organization ([42e7604](https://github.com/LindemannRock/craft-redirect-manager/commit/42e7604cab2f40d6f36ed155a09fb3bbf9cb402f))
* Update redirect creation to return new ID on success and enhance edit template with save options ([7c91fe3](https://github.com/LindemannRock/craft-redirect-manager/commit/7c91fe3abff9fb963e70055baa9a52b065cda18e))
* update smart caching description and add cache storage method configuration ([461b806](https://github.com/LindemannRock/craft-redirect-manager/commit/461b806440c26eaa83c6c9932f22785632ed7f0d))


### Bug Fixes

* **actionExport:** handle empty data case in export action ([7546545](https://github.com/LindemannRock/craft-redirect-manager/commit/754654510ec53031b6e1fa63408c0c7f15fdb9f2))
* add version to composer.json to fix release-please ([1d290d1](https://github.com/LindemannRock/craft-redirect-manager/commit/1d290d147b61aac04dff1cc2112111f215757b3d))
* **advanced-settings:** improve descriptions for quick setup and WordPress migration filters ([3f002c0](https://github.com/LindemannRock/craft-redirect-manager/commit/3f002c087474d2f5cfe83978dcd6d194a83db501))
* analytics handling in RedirectManager ([699185a](https://github.com/LindemannRock/craft-redirect-manager/commit/699185acdb70bb6fd2d7ec863b5bb9f7fd395afe))
* **AnalyticsController, ImportExportController:** update request handling for export actions ([ae54b75](https://github.com/LindemannRock/craft-redirect-manager/commit/ae54b759bdbc90b2bf4de364f7140f2f9815be97))
* **backup:** adjust margin style for backup settings header ([30c58fa](https://github.com/LindemannRock/craft-redirect-manager/commit/30c58fadbaa15831582593106e716fc88db9db27))
* CleanupAnalyticsJob with next run time calculation and display ([1d489ce](https://github.com/LindemannRock/craft-redirect-manager/commit/1d489ce4308f784f4ba268583361dc102575cf4e))
* **config:** clarify log level options comment ([4e7d405](https://github.com/LindemannRock/craft-redirect-manager/commit/4e7d405848085aeef1191269ab47e4427ac0b487))
* dynamically retrieve plugin names for better source plugin display ([87b46b3](https://github.com/LindemannRock/craft-redirect-manager/commit/87b46b363db3348802e7550f0bf4ec42ec3f4aea))
* enhance analytics CSV export with additional fields and improved layout ([9a6982d](https://github.com/LindemannRock/craft-redirect-manager/commit/9a6982d3a448a8e196f5b208ee947ada720da5a7))
* enhance backup creation logic to check for existing redirects and update template conditions ([1c5400f](https://github.com/LindemannRock/craft-redirect-manager/commit/1c5400f58f5c471b1b57f93aa026e1a542100d7a))
* enhance log level validation to ensure 'debug' is only allowed in devMode ([fc96a36](https://github.com/LindemannRock/craft-redirect-manager/commit/fc96a3683e5bee86937ffafa3da94f4e44847ce3))
* format cache file counts and update analytics button style ([4b4fd9b](https://github.com/LindemannRock/craft-redirect-manager/commit/4b4fd9b8edf0c3c1e02cb8b7b1f8be89bbb36301))
* **import-export:** remove unused menu button initialization code ([382c9a0](https://github.com/LindemannRock/craft-redirect-manager/commit/382c9a00c06c40640cb870729d5a652baa81624a))
* **ImportExportController, AnalyticsService:** replace DateTimeHelper with DateFormatHelper for date formatting ([a4a78fa](https://github.com/LindemannRock/craft-redirect-manager/commit/a4a78fac4ef9cfb3f348537bf45c1ee37b8419af))
* Improve AJAX request handling in dashboard refresh function ([5ae6a55](https://github.com/LindemannRock/craft-redirect-manager/commit/5ae6a5554e506da8dfe912cb2f2530c1df64a0b8))
* improve backup notification logic in import preview template ([5908836](https://github.com/LindemannRock/craft-redirect-manager/commit/5908836bc4052d19b8ba30ea76bc12ed50e71f3d))
* improve display name handling and trim whitespace in settings ([c6aee51](https://github.com/LindemannRock/craft-redirect-manager/commit/c6aee5148434e819d77f7c7a73f90a54e2ae91d1))
* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([1822b66](https://github.com/LindemannRock/craft-redirect-manager/commit/1822b66d5037a4f19ffc39ce18e560439c147414))
* **jobs:** implement RetryableJobInterface in job classes ([1f348a5](https://github.com/LindemannRock/craft-redirect-manager/commit/1f348a534d74cb5f4d1fef11886ed14ebafd2538))
* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([c1c3ea5](https://github.com/LindemannRock/craft-redirect-manager/commit/c1c3ea53fffd6ef70d5e0a5ae61241ba68af1962))
* logging documentation and update analytics handling in Redirect Manager ([f89928e](https://github.com/LindemannRock/craft-redirect-manager/commit/f89928eea793f033c636506f108e1c8d326ed42a))
* plugin name retrieval to use getFullName method for consistency ([d410744](https://github.com/LindemannRock/craft-redirect-manager/commit/d41074406dffb930faec9331666c9ba87f2ee8e1))
* Preserve user input in search parameter for URL params ([d61e117](https://github.com/LindemannRock/craft-redirect-manager/commit/d61e1175b17aa94b4a1a61bb94cfeb01301a2162))
* **RedirectManager:** update [@since](https://github.com/since) version in getCpSections method to 5.24.0 ([c5580f0](https://github.com/LindemannRock/craft-redirect-manager/commit/c5580f0506c6da1f28a06ab1f6cc42bad4bd7268))
* **RedirectsService:** 404 handling by stripping site base path ([ff7c7b1](https://github.com/LindemannRock/craft-redirect-manager/commit/ff7c7b18bc4e4cbeff6b786f29d00cc3aa7bf6b6))
* remove redundant redirects and analytics display limits from settings and config ([48f13d9](https://github.com/LindemannRock/craft-redirect-manager/commit/48f13d9015a53cdc5dc56ae1ff15b948ecaf6c8a))
* remove unnecessary blank line in import/export template ([485f8d2](https://github.com/LindemannRock/craft-redirect-manager/commit/485f8d21d4887ba488c5c6fee0a9efba46cb1a92))
* remove unused getBackupHistory method from RedirectManagerVariable ([3abaebc](https://github.com/LindemannRock/craft-redirect-manager/commit/3abaebca36eee520ff700ba08f447d90db0b6be0))
* reorganize and standardize analytics templates ([b493e43](https://github.com/LindemannRock/craft-redirect-manager/commit/b493e43ce6dcf0187a1df7637fa2a9479771da06))
* reorganize config settings for clarity and add new logging and caching options ([1a2bf44](https://github.com/LindemannRock/craft-redirect-manager/commit/1a2bf4451188bfb710f285970e6bc2075574e880))
* **security:** address multiple security vulnerabilities ([2d97c82](https://github.com/LindemannRock/craft-redirect-manager/commit/2d97c82e41a28f9496d07bb79ee6b97b1a0bf380))
* **security:** address XSS, permissions, cache, and CSV injection vulnerabilities ([1d42686](https://github.com/LindemannRock/craft-redirect-manager/commit/1d426868fb1b34a0173651cbd61b84e2dd7fb40d))
* **security:** validate URL schemes to prevent unsafe links in dashboard ([25a897f](https://github.com/LindemannRock/craft-redirect-manager/commit/25a897fe7645afd05045cfe67a3b7e8e5be189e6))
* **settings:** clarify device detection cache duration comment ([7e9477d](https://github.com/LindemannRock/craft-redirect-manager/commit/7e9477dfd6f43379755afcb7a0cd923e0e13c8c9))
* **SettingsController:** validate and sanitize settings section parameter ([580a01e](https://github.com/LindemannRock/craft-redirect-manager/commit/580a01e08f7673644a13a664b88402ffcc688d72))
* **settings:** remove redundant submit button from settings forms ([9dd2ff2](https://github.com/LindemannRock/craft-redirect-manager/commit/9dd2ff2c44680b613b6d748931c1d15e1e83ae95))
* **Settings:** update validation message for undoWindowMinutes ([5434224](https://github.com/LindemannRock/craft-redirect-manager/commit/5434224276f24647946dc58dfd3600bc0b6a603a))
* **settings:** validate integer settings and improve error handling ([495c806](https://github.com/LindemannRock/craft-redirect-manager/commit/495c806d7b107bea451af7195f44b9d4c8228aec))
* swap cache and backup settings links in the sidebar ([bb1b036](https://github.com/LindemannRock/craft-redirect-manager/commit/bb1b0362e40f6c77640a079ff9c4fac1286c79a6))
* update button label to clarify saving settings ([7f521e0](https://github.com/LindemannRock/craft-redirect-manager/commit/7f521e0b49ea03970cae92aab63f64ff27207194))
* update cache duration fields and improve instructions ([25be9a6](https://github.com/LindemannRock/craft-redirect-manager/commit/25be9a6e1b0dec7b9a7b634ed9661dac4d5f138f))
* update cache location message to use redirectHelper for dynamic path ([7e63e0e](https://github.com/LindemannRock/craft-redirect-manager/commit/7e63e0ee4c4f1c70724e898ea87d1935561c96c0))
* update filename generation and improve CSV import handling ([f619962](https://github.com/LindemannRock/craft-redirect-manager/commit/f6199622f8bec59f0efd2abae497f4b241700ece))
* Update filename generation to use 'alltime' instead of 'all' for clarity ([b2aa0ab](https://github.com/LindemannRock/craft-redirect-manager/commit/b2aa0ab93cbb566bf394c9f115acde71e0f0b7d4))
* Update form action URL in dashboard template ([3c94242](https://github.com/LindemannRock/craft-redirect-manager/commit/3c942428e7583595d6c4b771e31851453dd88602))
* update hardcoded cache paths with PluginHelper for consistency ([36cb9e0](https://github.com/LindemannRock/craft-redirect-manager/commit/36cb9e0ba9af30f81b6cbab91174620709af304d))
* update icon for Redirect Manager Utility ([ba08098](https://github.com/LindemannRock/craft-redirect-manager/commit/ba08098a203d411f629a103442261976728e3380))
* update icon path for Unhandled404sWidget ([ab06edd](https://github.com/LindemannRock/craft-redirect-manager/commit/ab06edddaa63062addb169ab49b8630475d3378a))
* update PluginHelper bootstrap to include download permissions for logging ([e6fa6b0](https://github.com/LindemannRock/craft-redirect-manager/commit/e6fa6b0eef0def38849ab7a11f01d92a5602fdda))
* Update search clear button selector in dashboard and redirects templates ([ce01cf1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce01cf1c9a49561f28bf815489624764a51f2e48))
* update success message for settings save action ([7d3dc52](https://github.com/LindemannRock/craft-redirect-manager/commit/7d3dc529531b939d50f27537433f438c7ccd964d))
* update time formatting in analytics dashboard to use locale settings ([3e79ff4](https://github.com/LindemannRock/craft-redirect-manager/commit/3e79ff417e0cf526ca210c246d262c88d0de80a3))
* validate analytics type parameter and replace getenv() ([44e99cd](https://github.com/LindemannRock/craft-redirect-manager/commit/44e99cdbea888b29b1f61324f734c852b03d6126))


### Miscellaneous Chores

* add .gitattributes with export-ignore for Packagist distribution ([cd904e3](https://github.com/LindemannRock/craft-redirect-manager/commit/cd904e36dd7cb5c7d899c0495ac7e5f983fb65a5))
* **main:** release 5.0.0 ([dd3988e](https://github.com/LindemannRock/craft-redirect-manager/commit/dd3988e9b9e22754d47fddac4ce748650b20616d))
* **main:** release 5.0.0 ([afafc3a](https://github.com/LindemannRock/craft-redirect-manager/commit/afafc3aa46a807c0c21f11a73a453bb833e0873d))
* **main:** release 5.1.0 ([52fbd90](https://github.com/LindemannRock/craft-redirect-manager/commit/52fbd909f75048db842592b80681d55a3c2b0a6e))
* **main:** release 5.1.0 ([06b717f](https://github.com/LindemannRock/craft-redirect-manager/commit/06b717ffdb18f3d1bce8ca11c6fb66db70eb766b))
* **main:** release 5.1.1 ([afc9869](https://github.com/LindemannRock/craft-redirect-manager/commit/afc98696152c07ecc67bb5087a0b9921ea53cfb7))
* **main:** release 5.1.1 ([2e9d588](https://github.com/LindemannRock/craft-redirect-manager/commit/2e9d588a4fb1104e3a01e4943a8e3c1756111ecf))
* **main:** release 5.1.2 ([950448b](https://github.com/LindemannRock/craft-redirect-manager/commit/950448b880add0802660be2e2696aca05b587f70))
* **main:** release 5.1.2 ([0e352ce](https://github.com/LindemannRock/craft-redirect-manager/commit/0e352ce17f9fbdea07b065780728c8bfe2107865))
* **main:** release 5.1.3 ([11d70c9](https://github.com/LindemannRock/craft-redirect-manager/commit/11d70c9db3db5cba338b702b2ffe22b72c500f22))
* **main:** release 5.1.3 ([72c8c68](https://github.com/LindemannRock/craft-redirect-manager/commit/72c8c688d2d309ff632ef6a198d0a7cb8da92eec))
* **main:** release 5.10.0 ([7972982](https://github.com/LindemannRock/craft-redirect-manager/commit/7972982bd2e2495fcbef143141be95f3fdd18060))
* **main:** release 5.10.0 ([4b285a5](https://github.com/LindemannRock/craft-redirect-manager/commit/4b285a56a442cad73a14079d9a5a3f8d50837374))
* **main:** release 5.11.0 ([f1eb6f4](https://github.com/LindemannRock/craft-redirect-manager/commit/f1eb6f4b954cbd7b25f7e0912f28da7730cb28e2))
* **main:** release 5.11.0 ([c2f2424](https://github.com/LindemannRock/craft-redirect-manager/commit/c2f242473d42f1ad511e62120ce2b2302c10f8c6))
* **main:** release 5.12.0 ([ef9445a](https://github.com/LindemannRock/craft-redirect-manager/commit/ef9445a1c8112d54471a612e97a69de16f248498))
* **main:** release 5.12.0 ([14e3224](https://github.com/LindemannRock/craft-redirect-manager/commit/14e32249323414746975434cc097b0b71b14bb6a))
* **main:** release 5.13.0 ([14497da](https://github.com/LindemannRock/craft-redirect-manager/commit/14497da3b5d48e6385214c1d26b1b5a42030dec7))
* **main:** release 5.13.0 ([27f7528](https://github.com/LindemannRock/craft-redirect-manager/commit/27f7528de0e2a04d82c1d184a87d5977c8357f06))
* **main:** release 5.14.0 ([e6747c2](https://github.com/LindemannRock/craft-redirect-manager/commit/e6747c2a49be195c34c06d96fd8a718b320ea372))
* **main:** release 5.14.0 ([bcc8ad3](https://github.com/LindemannRock/craft-redirect-manager/commit/bcc8ad3a0cf27c8875251870eb23515368c1cc4b))
* **main:** release 5.14.1 ([43f40d1](https://github.com/LindemannRock/craft-redirect-manager/commit/43f40d1ccf65aaffb7b931ae501443646776d4e7))
* **main:** release 5.14.1 ([9cc4ee0](https://github.com/LindemannRock/craft-redirect-manager/commit/9cc4ee0a997f1abaf1f4052e09060c07229db8b2))
* **main:** release 5.14.2 ([93779a2](https://github.com/LindemannRock/craft-redirect-manager/commit/93779a21721d67d6f6fbc8e2695d582efa2571a1))
* **main:** release 5.14.2 ([4ea919c](https://github.com/LindemannRock/craft-redirect-manager/commit/4ea919c9eafa5046d15a7d53c00fa05d59378173))
* **main:** release 5.14.3 ([8a3a001](https://github.com/LindemannRock/craft-redirect-manager/commit/8a3a001c08193a94ee6c26930a155221c7e333c1))
* **main:** release 5.14.3 ([0a68844](https://github.com/LindemannRock/craft-redirect-manager/commit/0a68844e019564f3a46fd7e4b358e859190f017c))
* **main:** release 5.14.4 ([ba0010a](https://github.com/LindemannRock/craft-redirect-manager/commit/ba0010a22b30aceaaa24a9214bc338092bf76bd9))
* **main:** release 5.14.4 ([8a45e54](https://github.com/LindemannRock/craft-redirect-manager/commit/8a45e54623b67fc7dbbcafe49e7a6c03d2459d78))
* **main:** release 5.15.0 ([691dbe8](https://github.com/LindemannRock/craft-redirect-manager/commit/691dbe8dfbe364b022b3cd70355d09fbdfd4d82f))
* **main:** release 5.15.0 ([cdf49d2](https://github.com/LindemannRock/craft-redirect-manager/commit/cdf49d2e930afeed4b6d637b8f61092df7a89715))
* **main:** release 5.16.0 ([e766d86](https://github.com/LindemannRock/craft-redirect-manager/commit/e766d86d730f9f995b2b6dae90bdaac302c2ed35))
* **main:** release 5.16.0 ([071bc9b](https://github.com/LindemannRock/craft-redirect-manager/commit/071bc9b838438de7d54189d7da11386f2beb7910))
* **main:** release 5.17.0 ([71f5b72](https://github.com/LindemannRock/craft-redirect-manager/commit/71f5b72a63f7ca9c9088fdce3b77b70e4b80b89a))
* **main:** release 5.17.0 ([b90dd6d](https://github.com/LindemannRock/craft-redirect-manager/commit/b90dd6df8235a47b4d5db04969e0f466ccbd0628))
* **main:** release 5.18.0 ([1edfbfa](https://github.com/LindemannRock/craft-redirect-manager/commit/1edfbfa13789d69ee17bd133375d31f676fd8532))
* **main:** release 5.18.0 ([a9f73f7](https://github.com/LindemannRock/craft-redirect-manager/commit/a9f73f7b64da2bad927721a627b8d7d7cc12ed1a))
* **main:** release 5.18.1 ([82eda42](https://github.com/LindemannRock/craft-redirect-manager/commit/82eda42f059c23e24e5d28b6698c57e8e2a0daa3))
* **main:** release 5.18.1 ([3748415](https://github.com/LindemannRock/craft-redirect-manager/commit/37484159d9c41315c15466c386d61510adf01912))
* **main:** release 5.18.2 ([32871ef](https://github.com/LindemannRock/craft-redirect-manager/commit/32871ef18d4f0cec234ae2ab8e8640c576a294f2))
* **main:** release 5.18.2 ([53e7a8a](https://github.com/LindemannRock/craft-redirect-manager/commit/53e7a8ab6f384dcc94bfea2ba5557c88d192a771))
* **main:** release 5.19.0 ([080dfb6](https://github.com/LindemannRock/craft-redirect-manager/commit/080dfb675dc6ee38cff0fb38b9b3a886d82df200))
* **main:** release 5.19.0 ([9d7e8c9](https://github.com/LindemannRock/craft-redirect-manager/commit/9d7e8c9f6d703e9fb29eb4d306d1d6dbcdf0a730))
* **main:** release 5.2.0 ([6e6f602](https://github.com/LindemannRock/craft-redirect-manager/commit/6e6f602a41a78372197a40f6acc53ef9e6d42848))
* **main:** release 5.2.0 ([3cc2cdf](https://github.com/LindemannRock/craft-redirect-manager/commit/3cc2cdf3e5ceeaafe601577fdc284298913a31e2))
* **main:** release 5.20.0 ([8054410](https://github.com/LindemannRock/craft-redirect-manager/commit/80544103111cc5af81d304eba174605c1cd58049))
* **main:** release 5.20.0 ([f23f030](https://github.com/LindemannRock/craft-redirect-manager/commit/f23f03090d896b397e77b4330be6fb3771eb0717))
* **main:** release 5.21.0 ([07352a7](https://github.com/LindemannRock/craft-redirect-manager/commit/07352a71d9bfb3211c8f5db43f8ac50a51b94582))
* **main:** release 5.21.0 ([8c93a18](https://github.com/LindemannRock/craft-redirect-manager/commit/8c93a18ea4016838e17785561286f8ce39672ba1))
* **main:** release 5.22.0 ([180b687](https://github.com/LindemannRock/craft-redirect-manager/commit/180b687b150f28774791e54e70d733535f2849e6))
* **main:** release 5.22.0 ([51e4038](https://github.com/LindemannRock/craft-redirect-manager/commit/51e403899f8b9cd859ab23eccba2a605667e5f4b))
* **main:** release 5.23.0 ([e5888c1](https://github.com/LindemannRock/craft-redirect-manager/commit/e5888c1a697cc848b04fe2a86eb3c757fe1d3c13))
* **main:** release 5.23.0 ([e5bed20](https://github.com/LindemannRock/craft-redirect-manager/commit/e5bed20bef8243794eadae8ba5ac07a54f9f459e))
* **main:** release 5.24.0 ([27b5a0e](https://github.com/LindemannRock/craft-redirect-manager/commit/27b5a0ef7bec49da8408186457399e6ca7beb57c))
* **main:** release 5.24.0 ([59101fb](https://github.com/LindemannRock/craft-redirect-manager/commit/59101fb3ab9a852247c0aa753e1c35e908d51111))
* **main:** release 5.24.1 ([3c896fd](https://github.com/LindemannRock/craft-redirect-manager/commit/3c896fdf7358c296bb37dbcc8981ebd81e545376))
* **main:** release 5.24.1 ([bb174a6](https://github.com/LindemannRock/craft-redirect-manager/commit/bb174a65233c0c4944f644c7abd8750306f51418))
* **main:** release 5.25.0 ([a891885](https://github.com/LindemannRock/craft-redirect-manager/commit/a8918851dbc16c0ffbcabd9cb287f2bd2626bfcd))
* **main:** release 5.25.0 ([fe54f5b](https://github.com/LindemannRock/craft-redirect-manager/commit/fe54f5bd2507333483ff115513ba3ddfd116a332))
* **main:** release 5.26.0 ([5b7a6cd](https://github.com/LindemannRock/craft-redirect-manager/commit/5b7a6cd097cd7ac455661309ae7313d9f478f920))
* **main:** release 5.26.0 ([f643a63](https://github.com/LindemannRock/craft-redirect-manager/commit/f643a63454bc3ff19369cedb505d3d811f622629))
* **main:** release 5.27.0 ([27c8e62](https://github.com/LindemannRock/craft-redirect-manager/commit/27c8e628413f13b08f13d3fa9b7dd61fb4634efe))
* **main:** release 5.27.0 ([e240a25](https://github.com/LindemannRock/craft-redirect-manager/commit/e240a25e45a482524b90e89a143d82790ed3a29f))
* **main:** release 5.28.0 ([95f1bc2](https://github.com/LindemannRock/craft-redirect-manager/commit/95f1bc23daa5a4dc029ace699d307d82a18639c3))
* **main:** release 5.28.0 ([26ac1db](https://github.com/LindemannRock/craft-redirect-manager/commit/26ac1dba1e5dcd15ad6da90b46a838a3cf0d0710))
* **main:** release 5.28.1 ([cd605af](https://github.com/LindemannRock/craft-redirect-manager/commit/cd605af4c6264b4141a8f87ade7304a1a3666b4e))
* **main:** release 5.28.1 ([b6233f1](https://github.com/LindemannRock/craft-redirect-manager/commit/b6233f166d719986d8fd131ea36fa4ac1cad594a))
* **main:** release 5.28.2 ([4c1e7fa](https://github.com/LindemannRock/craft-redirect-manager/commit/4c1e7fa397a6fd29cf555b488291d4f6ff77870e))
* **main:** release 5.28.2 ([664e00c](https://github.com/LindemannRock/craft-redirect-manager/commit/664e00cfed5425d7fd186157bb1c168c116b3032))
* **main:** release 5.3.0 ([c9e7677](https://github.com/LindemannRock/craft-redirect-manager/commit/c9e76771805674949b6de9fb849ed1da359bd8c9))
* **main:** release 5.3.0 ([ef8ed0c](https://github.com/LindemannRock/craft-redirect-manager/commit/ef8ed0ca26499efe18a2b6fde3650ff84922807c))
* **main:** release 5.3.1 ([a7a4fd5](https://github.com/LindemannRock/craft-redirect-manager/commit/a7a4fd5ecbfbedd6f8501e6acad93e0363dad0f1))
* **main:** release 5.3.1 ([6fe254d](https://github.com/LindemannRock/craft-redirect-manager/commit/6fe254d17178bb1395817b2abd0084f427d310e8))
* **main:** release 5.3.2 ([681cf4c](https://github.com/LindemannRock/craft-redirect-manager/commit/681cf4ccf7c364908175f0befe014b4c7da89ff9))
* **main:** release 5.3.2 ([3bec092](https://github.com/LindemannRock/craft-redirect-manager/commit/3bec09259d95150f6cf1673dd0a54fa8136d3a02))
* **main:** release 5.4.0 ([60f4235](https://github.com/LindemannRock/craft-redirect-manager/commit/60f42350ebf3e92e699f34db938d68f70ac74166))
* **main:** release 5.4.0 ([dc606c6](https://github.com/LindemannRock/craft-redirect-manager/commit/dc606c67b611fa9a6003d16a4261fc9ab45256df))
* **main:** release 5.5.0 ([29ff4b1](https://github.com/LindemannRock/craft-redirect-manager/commit/29ff4b1dc10f1ccd7fc8c98e5614b2c61755e5d3))
* **main:** release 5.5.0 ([d58742a](https://github.com/LindemannRock/craft-redirect-manager/commit/d58742a6a2b67702fc6354b08ea7e48650897b96))
* **main:** release 5.6.0 ([12d90fd](https://github.com/LindemannRock/craft-redirect-manager/commit/12d90fdd90a48b27774b66dc88781fd66915bab8))
* **main:** release 5.6.0 ([060ed8b](https://github.com/LindemannRock/craft-redirect-manager/commit/060ed8ba4ba356b3874b1b4cb8c3e833893e04b1))
* **main:** release 5.7.0 ([9c89937](https://github.com/LindemannRock/craft-redirect-manager/commit/9c899370efffca0d75f81931b927975c876ba270))
* **main:** release 5.7.0 ([77c2ceb](https://github.com/LindemannRock/craft-redirect-manager/commit/77c2cebef685ea2ff6e8a7fece64af6843324337))
* **main:** release 5.8.0 ([f66a1ee](https://github.com/LindemannRock/craft-redirect-manager/commit/f66a1ee94d6f408860c053a2e2e006f297db61ec))
* **main:** release 5.8.0 ([d7c25de](https://github.com/LindemannRock/craft-redirect-manager/commit/d7c25de0cdd51a06856b6f75d9168b73178b3427))
* **main:** release 5.8.1 ([48f8d30](https://github.com/LindemannRock/craft-redirect-manager/commit/48f8d3090a190e0f8c3e4c347b6bfcc1cfd1e0d0))
* **main:** release 5.8.1 ([1b8d82e](https://github.com/LindemannRock/craft-redirect-manager/commit/1b8d82e35c3dbc1847c6d77fb0485df951a87c83))
* **main:** release 5.8.2 ([70944bb](https://github.com/LindemannRock/craft-redirect-manager/commit/70944bb60f9bea2b99a97cb494e70881a99157ba))
* **main:** release 5.8.2 ([df4ee41](https://github.com/LindemannRock/craft-redirect-manager/commit/df4ee414c8e51e7a53d3b62072afe61cd5bf395d))
* **main:** release 5.9.0 ([993ca31](https://github.com/LindemannRock/craft-redirect-manager/commit/993ca31257f128c4190944f526d52c3d0fcf45c1))
* **main:** release 5.9.0 ([da8a4ac](https://github.com/LindemannRock/craft-redirect-manager/commit/da8a4ac31fb822e8648a4848d368d88108d32d12))
* remove unused dependency from composer.json ([7a3ba83](https://github.com/LindemannRock/craft-redirect-manager/commit/7a3ba839a29fe8d3c2138cfc91b047ccf3a71f0a))
* switch to Craft License for commercial release ([80816b0](https://github.com/LindemannRock/craft-redirect-manager/commit/80816b0ba91113752bd3f8b283764636a78ee039))
* update package.json with author and company details ([3d58f43](https://github.com/LindemannRock/craft-redirect-manager/commit/3d58f4338019a71f7022eb6264313203b474e521))
* update version annotations to reflect new versioning scheme across multiple files ([891ebe8](https://github.com/LindemannRock/craft-redirect-manager/commit/891ebe84a4deb5c35619532686413fa5edb25b29))
* **workflow:** update release-please.yml permissions and settings ([33bd4f4](https://github.com/LindemannRock/craft-redirect-manager/commit/33bd4f434d46f57517bfb1d67e0cf7c53fc6483c))

## [5.28.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.1...v5.28.2) (2026-03-18)


### Bug Fixes

* **config:** clarify log level options comment ([4e7d405](https://github.com/LindemannRock/craft-redirect-manager/commit/4e7d405848085aeef1191269ab47e4427ac0b487))

## [5.28.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.0...v5.28.1) (2026-03-17)


### Miscellaneous Chores

* **workflow:** update release-please.yml permissions and settings ([33bd4f4](https://github.com/LindemannRock/craft-redirect-manager/commit/33bd4f434d46f57517bfb1d67e0cf7c53fc6483c))

## [5.28.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.27.0...v5.28.0) (2026-03-17)


### Features

* **analytics:** streamline IP processing and remove redundancy ([88ba22c](https://github.com/LindemannRock/craft-redirect-manager/commit/88ba22c94b7978e011519caa455a8c423bdd78ad))

## [5.27.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.26.0...v5.27.0) (2026-03-17)


### Features

* **analytics:** implement build process and update asset management ([36d19eb](https://github.com/LindemannRock/craft-redirect-manager/commit/36d19ebe4f6fc45820bdaa48821ef8bd2948d26b))
* **redirect-manager:** add install experience configuration ([9c4344e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c4344ef16aaa8ada251457d6dc4904b1ef83367))


### Bug Fixes

* **import-export:** remove unused menu button initialization code ([382c9a0](https://github.com/LindemannRock/craft-redirect-manager/commit/382c9a00c06c40640cb870729d5a652baa81624a))
* **settings:** remove redundant submit button from settings forms ([9dd2ff2](https://github.com/LindemannRock/craft-redirect-manager/commit/9dd2ff2c44680b613b6d748931c1d15e1e83ae95))

## [5.26.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.25.0...v5.26.0) (2026-03-04)


### Features

* add complete EN/DE translation ([d534845](https://github.com/LindemannRock/craft-redirect-manager/commit/d534845a9def0b94782208cde21824bd5c10f405))


### Bug Fixes

* **jobs:** implement RetryableJobInterface in job classes ([1f348a5](https://github.com/LindemannRock/craft-redirect-manager/commit/1f348a534d74cb5f4d1fef11886ed14ebafd2538))
* **settings:** validate integer settings and improve error handling ([495c806](https://github.com/LindemannRock/craft-redirect-manager/commit/495c806d7b107bea451af7195f44b9d4c8228aec))

## [5.25.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.24.1...v5.25.0) (2026-02-24)


### Features

* **analytics:** enhance analytics data handling and visualization ([33973b4](https://github.com/LindemannRock/craft-redirect-manager/commit/33973b469c22dbdd81e19a6aaf0106191c08352a))
* fix nested permission pattern — remove viewRedirects ([4c34568](https://github.com/LindemannRock/craft-redirect-manager/commit/4c34568b4ff2de33e028d575ea5bbb6e44d16d44))


### Bug Fixes

* **AnalyticsController, ImportExportController:** update request handling for export actions ([ae54b75](https://github.com/LindemannRock/craft-redirect-manager/commit/ae54b759bdbc90b2bf4de364f7140f2f9815be97))
* **SettingsController:** validate and sanitize settings section parameter ([580a01e](https://github.com/LindemannRock/craft-redirect-manager/commit/580a01e08f7673644a13a664b88402ffcc688d72))
* **Settings:** update validation message for undoWindowMinutes ([5434224](https://github.com/LindemannRock/craft-redirect-manager/commit/5434224276f24647946dc58dfd3600bc0b6a603a))
* validate analytics type parameter and replace getenv() ([44e99cd](https://github.com/LindemannRock/craft-redirect-manager/commit/44e99cdbea888b29b1f61324f734c852b03d6126))


### Miscellaneous Chores

* add .gitattributes with export-ignore for Packagist distribution ([cd904e3](https://github.com/LindemannRock/craft-redirect-manager/commit/cd904e36dd7cb5c7d899c0495ac7e5f983fb65a5))
* switch to Craft License for commercial release ([80816b0](https://github.com/LindemannRock/craft-redirect-manager/commit/80816b0ba91113752bd3f8b283764636a78ee039))

## [5.24.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.24.0...v5.24.1) (2026-02-07)


### Bug Fixes

* **ImportExportController, AnalyticsService:** replace DateTimeHelper with DateFormatHelper for date formatting ([a4a78fa](https://github.com/LindemannRock/craft-redirect-manager/commit/a4a78fac4ef9cfb3f348537bf45c1ee37b8419af))

## [5.24.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.23.0...v5.24.0) (2026-02-05)


### Features

* **backups:** Implement backup functionality for redirects ([62a9fb1](https://github.com/LindemannRock/craft-redirect-manager/commit/62a9fb1ab8a95f904d47bab4cdc811256f600ff1))
* **import-export:** add clear import history functionality and view history permissions ([fa194ca](https://github.com/LindemannRock/craft-redirect-manager/commit/fa194ca007ec3ed3fff3602eaad5f87e27a86a9c))
* **import-export:** add import limits to controller and template ([35b7519](https://github.com/LindemannRock/craft-redirect-manager/commit/35b75192f28fa7a795165b1e381bbc49e1e6da71))


### Bug Fixes

* **actionExport:** handle empty data case in export action ([7546545](https://github.com/LindemannRock/craft-redirect-manager/commit/754654510ec53031b6e1fa63408c0c7f15fdb9f2))
* **RedirectManager:** update [@since](https://github.com/since) version in getCpSections method to 5.24.0 ([c5580f0](https://github.com/LindemannRock/craft-redirect-manager/commit/c5580f0506c6da1f28a06ab1f6cc42bad4bd7268))
* **RedirectsService:** 404 handling by stripping site base path ([ff7c7b1](https://github.com/LindemannRock/craft-redirect-manager/commit/ff7c7b18bc4e4cbeff6b786f29d00cc3aa7bf6b6))


### Miscellaneous Chores

* remove unused dependency from composer.json ([7a3ba83](https://github.com/LindemannRock/craft-redirect-manager/commit/7a3ba839a29fe8d3c2138cfc91b047ccf3a71f0a))
* update package.json with author and company details ([3d58f43](https://github.com/LindemannRock/craft-redirect-manager/commit/3d58f4338019a71f7022eb6264313203b474e521))

## [5.23.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.22.0...v5.23.0) (2026-01-28)


### Features

* enhance redirects listing page and improve dashboard settings ([51d56f1](https://github.com/LindemannRock/craft-redirect-manager/commit/51d56f1b2b051de6464b9c2e80b5247d08d38034))

## [5.22.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.21.0...v5.22.0) (2026-01-26)


### Features

* replace direct plugin access with PluginHelper methods ([9ec3fc5](https://github.com/LindemannRock/craft-redirect-manager/commit/9ec3fc543d82711d5447a714951a412aad796c09))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([c1c3ea5](https://github.com/LindemannRock/craft-redirect-manager/commit/c1c3ea53fffd6ef70d5e0a5ae61241ba68af1962))
* **security:** address XSS, permissions, cache, and CSV injection vulnerabilities ([1d42686](https://github.com/LindemannRock/craft-redirect-manager/commit/1d426868fb1b34a0173651cbd61b84e2dd7fb40d))
* **security:** validate URL schemes to prevent unsafe links in dashboard ([25a897f](https://github.com/LindemannRock/craft-redirect-manager/commit/25a897fe7645afd05045cfe67a3b7e8e5be189e6))

## [5.21.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.20.0...v5.21.0) (2026-01-21)


### Features

* Add configurable geo IP provider settings with HTTPS support ([633cc5d](https://github.com/LindemannRock/craft-redirect-manager/commit/633cc5d8b937f798c98bf82f890b63358e5e811a))
* Enhance URL validation and error reporting in import/export process ([38f879c](https://github.com/LindemannRock/craft-redirect-manager/commit/38f879c424f23b22eafa06badd3bf8fe43d48fd2))
* Refactor backup and error messaging components in import/export templates ([2a7c903](https://github.com/LindemannRock/craft-redirect-manager/commit/2a7c9038068dcdcb6ff081b145eaee174172285a))


### Bug Fixes

* **security:** address multiple security vulnerabilities ([2d97c82](https://github.com/LindemannRock/craft-redirect-manager/commit/2d97c82e41a28f9496d07bb79ee6b97b1a0bf380))
* swap cache and backup settings links in the sidebar ([bb1b036](https://github.com/LindemannRock/craft-redirect-manager/commit/bb1b0362e40f6c77640a079ff9c4fac1286c79a6))

## [5.20.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.19.0...v5.20.0) (2026-01-16)


### Features

* add site options to redirect edit form and improve template structure ([c1acf3d](https://github.com/LindemannRock/craft-redirect-manager/commit/c1acf3dd56478451a970dde8d7c737f7ec43482c))


### Bug Fixes

* reorganize and standardize analytics templates ([b493e43](https://github.com/LindemannRock/craft-redirect-manager/commit/b493e43ce6dcf0187a1df7637fa2a9479771da06))
* update button label to clarify saving settings ([7f521e0](https://github.com/LindemannRock/craft-redirect-manager/commit/7f521e0b49ea03970cae92aab63f64ff27207194))
* update cache location message to use redirectHelper for dynamic path ([7e63e0e](https://github.com/LindemannRock/craft-redirect-manager/commit/7e63e0ee4c4f1c70724e898ea87d1935561c96c0))
* update filename generation and improve CSV import handling ([f619962](https://github.com/LindemannRock/craft-redirect-manager/commit/f6199622f8bec59f0efd2abae497f4b241700ece))
* update hardcoded cache paths with PluginHelper for consistency ([36cb9e0](https://github.com/LindemannRock/craft-redirect-manager/commit/36cb9e0ba9af30f81b6cbab91174620709af304d))
* update PluginHelper bootstrap to include download permissions for logging ([e6fa6b0](https://github.com/LindemannRock/craft-redirect-manager/commit/e6fa6b0eef0def38849ab7a11f01d92a5602fdda))

## [5.19.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.2...v5.19.0) (2026-01-12)


### Features

* add analytics count retrieval to RedirectManagerUtility ([c8204ca](https://github.com/LindemannRock/craft-redirect-manager/commit/c8204ca60264a8dcea96cce8a75bd15f734327c3))


### Bug Fixes

* format cache file counts and update analytics button style ([4b4fd9b](https://github.com/LindemannRock/craft-redirect-manager/commit/4b4fd9b8edf0c3c1e02cb8b7b1f8be89bbb36301))
* update icon path for Unhandled404sWidget ([ab06edd](https://github.com/LindemannRock/craft-redirect-manager/commit/ab06edddaa63062addb169ab49b8630475d3378a))

## [5.18.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.1...v5.18.2) (2026-01-12)


### Bug Fixes

* remove redundant redirects and analytics display limits from settings and config ([48f13d9](https://github.com/LindemannRock/craft-redirect-manager/commit/48f13d9015a53cdc5dc56ae1ff15b948ecaf6c8a))

## [5.18.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.0...v5.18.1) (2026-01-11)


### Bug Fixes

* plugin name retrieval to use getFullName method for consistency ([d410744](https://github.com/LindemannRock/craft-redirect-manager/commit/d41074406dffb930faec9331666c9ba87f2ee8e1))

## [5.18.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.17.0...v5.18.0) (2026-01-10)


### Features

* Add redirect analytics functionality with new template and service methods ([303faad](https://github.com/LindemannRock/craft-redirect-manager/commit/303faade49718b84d5c2c7221ef6aef527584bac))

## [5.17.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.16.0...v5.17.0) (2026-01-09)


### Features

* Update backup path structure to include 'imports' subdirectory for better organization ([42e7604](https://github.com/LindemannRock/craft-redirect-manager/commit/42e7604cab2f40d6f36ed155a09fb3bbf9cb402f))

## [5.16.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.15.0...v5.16.0) (2026-01-09)


### Features

* Enhance import/export functionality with improved URL parsing and auto-detection of creation type ([c5f8b20](https://github.com/LindemannRock/craft-redirect-manager/commit/c5f8b202ec5928353c04f51c0c5e0767034d326b))
* Update redirect creation to return new ID on success and enhance edit template with save options ([7c91fe3](https://github.com/LindemannRock/craft-redirect-manager/commit/7c91fe3abff9fb963e70055baa9a52b065cda18e))

## [5.15.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.4...v5.15.0) (2026-01-09)


### Features

* Enhance redirect matching to return all matches with resolved destinations ([53ca0da](https://github.com/LindemannRock/craft-redirect-manager/commit/53ca0da84cf8571253a43a9d8da8fd973a3ea5c7))


### Bug Fixes

* Update filename generation to use 'alltime' instead of 'all' for clarity ([b2aa0ab](https://github.com/LindemannRock/craft-redirect-manager/commit/b2aa0ab93cbb566bf394c9f115acde71e0f0b7d4))

## [5.14.4](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.3...v5.14.4) (2026-01-08)


### Bug Fixes

* Update search clear button selector in dashboard and redirects templates ([ce01cf1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce01cf1c9a49561f28bf815489624764a51f2e48))

## [5.14.3](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.2...v5.14.3) (2026-01-08)


### Bug Fixes

* Improve AJAX request handling in dashboard refresh function ([5ae6a55](https://github.com/LindemannRock/craft-redirect-manager/commit/5ae6a5554e506da8dfe912cb2f2530c1df64a0b8))

## [5.14.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.1...v5.14.2) (2026-01-08)


### Bug Fixes

* Preserve user input in search parameter for URL params ([d61e117](https://github.com/LindemannRock/craft-redirect-manager/commit/d61e1175b17aa94b4a1a61bb94cfeb01301a2162))

## [5.14.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.0...v5.14.1) (2026-01-08)


### Bug Fixes

* Update form action URL in dashboard template ([3c94242](https://github.com/LindemannRock/craft-redirect-manager/commit/3c942428e7583595d6c4b771e31851453dd88602))

## [5.14.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.13.0...v5.14.0) (2026-01-08)


### Features

* Add exportAnalytics permission and fix dashboard permissions ([843b4a1](https://github.com/LindemannRock/craft-redirect-manager/commit/843b4a1ad46eb227e08d0549f8574a949c136ed1))
* Add granular permissions and dynamic naming to redirect-manager ([60303af](https://github.com/LindemannRock/craft-redirect-manager/commit/60303af8c41b038a3922f753aa9f0cc7228b5a22))
* Simplify user permissions by grouping redirect management actions ([9230f3a](https://github.com/LindemannRock/craft-redirect-manager/commit/9230f3aa89e4023c8b4982e17a1c7625372e80c6))


### Bug Fixes

* update success message for settings save action ([7d3dc52](https://github.com/LindemannRock/craft-redirect-manager/commit/7d3dc529531b939d50f27537433f438c7ccd964d))

## [5.13.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.12.0...v5.13.0) (2026-01-05)


### Features

* migrate to lindemannrock/craft-plugin-base ([cf63978](https://github.com/LindemannRock/craft-redirect-manager/commit/cf6397815862f5c48746dc6fa6dbfb22ce9d2bbd))

## [5.12.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.11.0...v5.12.0) (2026-01-05)


### Features

* enhance analytics and redirects controllers with additional fields and improve dashboard UI ([90bad19](https://github.com/LindemannRock/craft-redirect-manager/commit/90bad19252f43b83b65a0147a43772a1b573d9ec))
* enhance dashboard and redirects UI with additional color coding for request and match types ([9c5bf9e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c5bf9e224351eeda533d725811b26359aec05e8))

## [5.11.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.10.0...v5.11.0) (2026-01-05)


### Features

* implement AJAX-based dashboard data retrieval and auto-refresh functionality ([a64a83f](https://github.com/LindemannRock/craft-redirect-manager/commit/a64a83fae422fb6661aa630910494e6d0e64622a))
* **redirect-manager:** dashboard UI improvements and bug fixes ([dec1968](https://github.com/LindemannRock/craft-redirect-manager/commit/dec196864b9152c68873030a20f1e6cb9b56d8bc))

## [5.10.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.9.0...v5.10.0) (2026-01-04)


### Features

* add regex capture group support ($1, $2, etc.) for redirect destinations ([56743b8](https://github.com/LindemannRock/craft-redirect-manager/commit/56743b890bd4608d9e1badf1094719343503197a))

## [5.9.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.2...v5.9.0) (2025-12-19)


### Features

* Add geographic analytics and traffic analysis tabs with detailed statistics ([1e986e3](https://github.com/LindemannRock/craft-redirect-manager/commit/1e986e33745b18b1fb1fe3575e71cc98f71a548d))
* add geographic analytics for top countries and cities ([de5b98f](https://github.com/LindemannRock/craft-redirect-manager/commit/de5b98f5c3ef7ef6a93b0c106895ad8e55c1da39))


### Bug Fixes

* improve display name handling and trim whitespace in settings ([c6aee51](https://github.com/LindemannRock/craft-redirect-manager/commit/c6aee5148434e819d77f7c7a73f90a54e2ae91d1))
* update cache duration fields and improve instructions ([25be9a6](https://github.com/LindemannRock/craft-redirect-manager/commit/25be9a6e1b0dec7b9a7b634ed9661dac4d5f138f))

## [5.8.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.1...v5.8.2) (2025-12-16)


### Bug Fixes

* update icon for Redirect Manager Utility ([ba08098](https://github.com/LindemannRock/craft-redirect-manager/commit/ba08098a203d411f629a103442261976728e3380))

## [5.8.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.0...v5.8.1) (2025-12-16)


### Bug Fixes

* update time formatting in analytics dashboard to use locale settings ([3e79ff4](https://github.com/LindemannRock/craft-redirect-manager/commit/3e79ff417e0cf526ca210c246d262c88d0de80a3))

## [5.8.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.7.0...v5.8.0) (2025-12-16)


### Features

* add cache storage method configuration for different environments ([287fa40](https://github.com/LindemannRock/craft-redirect-manager/commit/287fa40364851c136068679d313fdefae82ccd92))
* add cache storage method configuration to settings table ([6b9e424](https://github.com/LindemannRock/craft-redirect-manager/commit/6b9e424c192355548251f65561343e7e9d575d89))
* add SVG icon for redirect manager ([33af8ee](https://github.com/LindemannRock/craft-redirect-manager/commit/33af8eee101beb1912c1e1b816591d34bb2ecf24))
* enhance analytics data handling and timezone conversion; improve dashboard pagination links ([8e72a18](https://github.com/LindemannRock/craft-redirect-manager/commit/8e72a189eeb5188a20de6e02b7f1304fb95d659d))
* enhance cache status display and button functionality based on storage method ([e2ed5dc](https://github.com/LindemannRock/craft-redirect-manager/commit/e2ed5dcec1f96f568ba72168acb43a0df66afef6))
* implement cache storage method configuration and handling for Redis and file systems ([6b6058d](https://github.com/LindemannRock/craft-redirect-manager/commit/6b6058d5bd0f5f6fd37b978ebf77e8559bcdfd3c))
* update smart caching description and add cache storage method configuration ([461b806](https://github.com/LindemannRock/craft-redirect-manager/commit/461b806440c26eaa83c6c9932f22785632ed7f0d))

## [5.7.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.6.0...v5.7.0) (2025-12-03)


### Features

* add PHPStan and EasyCodingStandard configurations; enhance code quality checks ([0727817](https://github.com/LindemannRock/craft-redirect-manager/commit/0727817203d8620d0830c4eecf8857584b1868ef))
* **analytics:** enhance date range filtering and export functionality ([e15086f](https://github.com/LindemannRock/craft-redirect-manager/commit/e15086fa170af759b99dd1b91e1f0691ad19cefd))
* **info-box:** add new Info Box component for displaying informational notices ([353777e](https://github.com/LindemannRock/craft-redirect-manager/commit/353777e962289b40a090e6d0ec1720e46f99b7ce))
* **settings:** add default country and city for local development ([ad34d13](https://github.com/LindemannRock/craft-redirect-manager/commit/ad34d1331b29bb8059759c234567a706d4395c55))


### Bug Fixes

* **settings:** clarify device detection cache duration comment ([7e9477d](https://github.com/LindemannRock/craft-redirect-manager/commit/7e9477dfd6f43379755afcb7a0cd923e0e13c8c9))


### Miscellaneous Chores

* update version annotations to reflect new versioning scheme across multiple files ([891ebe8](https://github.com/LindemannRock/craft-redirect-manager/commit/891ebe84a4deb5c35619532686413fa5edb25b29))

## [5.6.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.5.0...v5.6.0) (2025-11-15)


### Features

* **license:** add MIT License file to the repository ([649ff9e](https://github.com/LindemannRock/craft-redirect-manager/commit/649ff9e28aae8d3310d670653202688c26fc8c8d))


### Bug Fixes

* **backup:** adjust margin style for backup settings header ([30c58fa](https://github.com/LindemannRock/craft-redirect-manager/commit/30c58fadbaa15831582593106e716fc88db9db27))

## [5.5.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.4.0...v5.5.0) (2025-11-14)


### Features

* **plugin:** enhance plugin name handling and introduce Twig extension for name variations ([a73917f](https://github.com/LindemannRock/craft-redirect-manager/commit/a73917f31972adc31f34546338ce8049e3492c98))
* **settings:** allow disabling undo window and improve site handling in redirects ([53903de](https://github.com/LindemannRock/craft-redirect-manager/commit/53903de93e8c8a7d2007a0e960024104b1b4a40d))

## [5.4.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.2...v5.4.0) (2025-11-11)


### Features

* **settings:** add WordPress migration filters and update settings description ([ce424e1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce424e193c89e73627feaa287a7dd38416b5a4d4))


### Bug Fixes

* **advanced-settings:** improve descriptions for quick setup and WordPress migration filters ([3f002c0](https://github.com/LindemannRock/craft-redirect-manager/commit/3f002c087474d2f5cfe83978dcd6d194a83db501))

## [5.3.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.1...v5.3.2) (2025-11-11)


### Bug Fixes

* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([1822b66](https://github.com/LindemannRock/craft-redirect-manager/commit/1822b66d5037a4f19ffc39ce18e560439c147414))

## [5.3.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.0...v5.3.1) (2025-11-07)


### Bug Fixes

* CleanupAnalyticsJob with next run time calculation and display ([1d489ce](https://github.com/LindemannRock/craft-redirect-manager/commit/1d489ce4308f784f4ba268583361dc102575cf4e))

## [5.3.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.2.0...v5.3.0) (2025-11-07)


### Features

* add backup path and volume UID to settings table; remove import history table ([33eb038](https://github.com/LindemannRock/craft-redirect-manager/commit/33eb038d57d299fd714850a712f1ad0fb29addd1))
* Centralize redirect integration with undo detection and notifications ([e9b3d95](https://github.com/LindemannRock/craft-redirect-manager/commit/e9b3d95814b8b0be27f333ea50234bbf87249c50))


### Bug Fixes

* dynamically retrieve plugin names for better source plugin display ([87b46b3](https://github.com/LindemannRock/craft-redirect-manager/commit/87b46b363db3348802e7550f0bf4ec42ec3f4aea))
* enhance analytics CSV export with additional fields and improved layout ([9a6982d](https://github.com/LindemannRock/craft-redirect-manager/commit/9a6982d3a448a8e196f5b208ee947ada720da5a7))
* improve backup notification logic in import preview template ([5908836](https://github.com/LindemannRock/craft-redirect-manager/commit/5908836bc4052d19b8ba30ea76bc12ed50e71f3d))
* remove unused getBackupHistory method from RedirectManagerVariable ([3abaebc](https://github.com/LindemannRock/craft-redirect-manager/commit/3abaebca36eee520ff700ba08f447d90db0b6be0))

## [5.2.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.3...v5.2.0) (2025-11-03)


### Features

* add setter method support and enhance config override checks for settings ([f1c6f1c](https://github.com/LindemannRock/craft-redirect-manager/commit/f1c6f1c730eb5b41ca229c4999c591cf49c585b8))


### Bug Fixes

* enhance backup creation logic to check for existing redirects and update template conditions ([1c5400f](https://github.com/LindemannRock/craft-redirect-manager/commit/1c5400f58f5c471b1b57f93aa026e1a542100d7a))
* logging documentation and update analytics handling in Redirect Manager ([f89928e](https://github.com/LindemannRock/craft-redirect-manager/commit/f89928eea793f033c636506f108e1c8d326ed42a))
* remove unnecessary blank line in import/export template ([485f8d2](https://github.com/LindemannRock/craft-redirect-manager/commit/485f8d21d4887ba488c5c6fee0a9efba46cb1a92))

## [5.1.3](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.2...v5.1.3) (2025-10-26)


### Bug Fixes

* enhance log level validation to ensure 'debug' is only allowed in devMode ([fc96a36](https://github.com/LindemannRock/craft-redirect-manager/commit/fc96a3683e5bee86937ffafa3da94f4e44847ce3))

## [5.1.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.1...v5.1.2) (2025-10-26)


### Bug Fixes

* reorganize config settings for clarity and add new logging and caching options ([1a2bf44](https://github.com/LindemannRock/craft-redirect-manager/commit/1a2bf4451188bfb710f285970e6bc2075574e880))

## [5.1.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.0...v5.1.1) (2025-10-26)


### Bug Fixes

* add version to composer.json to fix release-please ([1d290d1](https://github.com/LindemannRock/craft-redirect-manager/commit/1d290d147b61aac04dff1cc2112111f215757b3d))

## [5.1.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.0.0...v5.1.0) (2025-10-26)


### Features

* add analytics system, dashboard widgets, device detection, and IP privacy ([f428a1d](https://github.com/LindemannRock/craft-redirect-manager/commit/f428a1d8d350790a51b5acbce665d5f3bb324d38))
* add tooltip for custom headers in no-cache settings ([f0bfcab](https://github.com/LindemannRock/craft-redirect-manager/commit/f0bfcabee48c02084171957d8b36260b61e6d9ce))
* add utility page with system monitoring and cache management ([6e78b12](https://github.com/LindemannRock/craft-redirect-manager/commit/6e78b12bbd3ff9fb9d3b6da4aff0abd520900e6b))
* enhance templates to use dynamic plugin name for titles and labels ([2629559](https://github.com/LindemannRock/craft-redirect-manager/commit/2629559494a4f9ff2a893c43d44da8a554b0dc22))
* implement logging improvements across controllers and services ([0f5b463](https://github.com/LindemannRock/craft-redirect-manager/commit/0f5b4632fb797325b29d74350bac44aead7ce68f))


### Bug Fixes

* analytics handling in RedirectManager ([699185a](https://github.com/LindemannRock/craft-redirect-manager/commit/699185acdb70bb6fd2d7ec863b5bb9f7fd395afe))

## 5.0.0 (2025-10-21)


### Features

* initial Redirect Manager plugin implementation ([153c2ab](https://github.com/LindemannRock/craft-redirect-manager/commit/153c2aba744c196d8b7ea445c329bb87b179b664))
