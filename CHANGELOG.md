# Changelog

## [5.35.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.35.0...v5.35.1) (2026-06-29)


### Fixed

* **widgets:** pass dynamic chart ID to stats summary widget ([f798f59](https://github.com/LindemannRock/craft-redirect-manager/commit/f798f5956534144bde1547b6407d131ce091221a))

## [5.35.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.34.0...v5.35.0) - 2026-06-28


### Added

* add smoke test scripts for Craft compatibility checks ([8df71a7](https://github.com/LindemannRock/craft-redirect-manager/commit/8df71a7f00150cfdd03f51b7b94ac44474113be0))
* **api:** add rate limiting for JSON API requests ([39b93a4](https://github.com/LindemannRock/craft-redirect-manager/commit/39b93a4713c1f71a3e8396cc5fd8f7a3cfe3b4b1))
* document backup validation methods and integrity check ([9cf2b1a](https://github.com/LindemannRock/craft-redirect-manager/commit/9cf2b1a94dea119b014a71e90d9c503bd592004c))
* **i18n:** add developer resources translations across multiple locales ([7b3ec1e](https://github.com/LindemannRock/craft-redirect-manager/commit/7b3ec1e03e24db5c9e3b9b49a85fa84fb8ff0214))
* **i18n:** add filter labels for redirects and dashboard templates ([2c876d4](https://github.com/LindemannRock/craft-redirect-manager/commit/2c876d4647474e326b8c527aa13a47fc341b26b0))
* **redirects:** add site ID handling for redirect resolution ([5fbf152](https://github.com/LindemannRock/craft-redirect-manager/commit/5fbf1520c5d564d7b6fc723e1145e2763ae3bcf6))
* **redirects:** add siteIdKey for redirect uniqueness handling ([a4ad1c6](https://github.com/LindemannRock/craft-redirect-manager/commit/a4ad1c608f9384c218c00a6efe4fa863a53221ef))
* **redirects:** get enabled redirects for editable site IDs ([db13ddb](https://github.com/LindemannRock/craft-redirect-manager/commit/db13ddba0580d7e003300dcddb07b4de5067bb7f))
* **settings:** add action to download Postman collection and environment template ([f0e6d32](https://github.com/LindemannRock/craft-redirect-manager/commit/f0e6d322f94b5eb98a57d386767177abb361432d))


### Fixed

* **analytics:** correct country and city count aggregation logic ([cab1a40](https://github.com/LindemannRock/craft-redirect-manager/commit/cab1a40d49b2f1d71664cea38157276e870d0e77))
* **analytics:** make referrer column sortable and hideable in dashboard ([bf083c1](https://github.com/LindemannRock/craft-redirect-manager/commit/bf083c18242aaac5f33a56f51f12757713e317d8))
* **analytics:** remove 'bot-stats' type from valid request types ([d6c7214](https://github.com/LindemannRock/craft-redirect-manager/commit/d6c7214130cdab7c76d802b8e39a2e2b050f4419))
* **backups:** correct backup location display in info box ([2c6633c](https://github.com/LindemannRock/craft-redirect-manager/commit/2c6633cb2780a80c1a8e3587892d0a01809a5cf9))
* encode validRows count in import form submission confirmation ([1d4cfaf](https://github.com/LindemannRock/craft-redirect-manager/commit/1d4cfafef3baefca7a5beffc2b5a5b57ef2215aa))
* **i18n:** correct redirect messages for user notifications ([348f91d](https://github.com/LindemannRock/craft-redirect-manager/commit/348f91db84961885ee3dcdadb1a8968cef7d6421))
* **i18n:** correct translations across multiple locales ([d98cba6](https://github.com/LindemannRock/craft-redirect-manager/commit/d98cba67abe7a52039c56f62d6102e64e576c6e2))
* require explicit local geo defaults ([4211bd3](https://github.com/LindemannRock/craft-redirect-manager/commit/4211bd3d2d90ddb85772c54622d9464a16256aa7))
* update widget version annotation to 5.33.0 ([2e8b2d9](https://github.com/LindemannRock/craft-redirect-manager/commit/2e8b2d925a69086fa538d7bcc16764e094104ccb))
* **widget:** replace analytics retrieval with unhandled 404s method ([e6ceb53](https://github.com/LindemannRock/craft-redirect-manager/commit/e6ceb53171a13f581de0c02f7258dffc6994a58b))

## [5.34.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.33.0...v5.34.0) - 2026-06-20


### Added

* **i18n:** correct translations for redirect and analytics prompts ([95ae8f6](https://github.com/LindemannRock/craft-redirect-manager/commit/95ae8f6d251474ae3f1d9851a65aeefe4d7fd4dc))

## [5.33.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.32.1...v5.33.0) - 2026-06-18


### Added

* **analytics:** add bot detection and request type handling in analytics ([383e815](https://github.com/LindemannRock/craft-redirect-manager/commit/383e815cfdf982c8f2c58a43a48902fd4a7449ca))
* **analytics:** add system request type detection and handling ([e705654](https://github.com/LindemannRock/craft-redirect-manager/commit/e705654846feae5a6b1129c96633089840f2aace))
* **api:** add JSON redirects API endpoint ([96a9d33](https://github.com/LindemannRock/craft-redirect-manager/commit/96a9d330699478e11e9433e630cad4fd9d7832e6))
* **dashboard:** add bulk actions for clearing analytics records ([50edf8e](https://github.com/LindemannRock/craft-redirect-manager/commit/50edf8ed3c079f2f194ccf5d84129b152eef0a50))
* enrich redirect analytics traffic exports ([c24d615](https://github.com/LindemannRock/craft-redirect-manager/commit/c24d615ec557c1be9e85773fa7bfd566a4524d6d))
* **gql:** add GraphQL support ([bd2ab4a](https://github.com/LindemannRock/craft-redirect-manager/commit/bd2ab4ae8f5c0695201081b5fcd3da48a364c9df))
* **gql:** register dynamic redirect query with plugin name ([2f35e42](https://github.com/LindemannRock/craft-redirect-manager/commit/2f35e42ed90ea23f36952a7148ca6914b6bc9fa3))
* **i18n:** add "View all analytics" translation across multiple locales ([7ce66eb](https://github.com/LindemannRock/craft-redirect-manager/commit/7ce66ebcf99a3022204f0f607952500e6c2f12d2))
* **import-export:** enhance CSV export functionality with format support ([706ce32](https://github.com/LindemannRock/craft-redirect-manager/commit/706ce329b565b4e27e41a8eb0a9c43da676baa46))
* **import-export:** enhance URL validation and capture reference checks ([a574885](https://github.com/LindemannRock/craft-redirect-manager/commit/a57488580aa517a6de2a9dc338131099d8dd794e))
* **tests:** add analytics export data tests with enriched metadata ([b3e119a](https://github.com/LindemannRock/craft-redirect-manager/commit/b3e119ab9ff51c640086e5c4a89ebf1fd9424e4e))
* **tests:** add integration tests for redirect resolution functionality ([a20faeb](https://github.com/LindemannRock/craft-redirect-manager/commit/a20faeb1da3ff7593ba131e0abb149856273b869))
* **tests:** add manual CSV fixtures for testing import flow ([4e99ca0](https://github.com/LindemannRock/craft-redirect-manager/commit/4e99ca0a6bd132c584eccab16a7477d60d9cd614))
* **widgets:** add site filtering to analytics and 404s widgets ([c98b0f0](https://github.com/LindemannRock/craft-redirect-manager/commit/c98b0f0e8ba7d16dac6c29d7c93555ba6a262c2d))


### Fixed

* **i18n:** correct phrasing in cache clearing confirmation message ([f877272](https://github.com/LindemannRock/craft-redirect-manager/commit/f877272d515a167f78ac188e0eb512c04f480bb5))
* **i18n:** correct translations across multiple locales ([ab80da6](https://github.com/LindemannRock/craft-redirect-manager/commit/ab80da619809e495b682d1b22d5a532d903a2dad))
* **i18n:** correct translations for pipe and tab symbol in multiple locales ([c4dc468](https://github.com/LindemannRock/craft-redirect-manager/commit/c4dc46809fdbfbb34b558b79b26ffb03fd36f7b2))
* **i18n:** update translation keys and locale strings ([1332f8e](https://github.com/LindemannRock/craft-redirect-manager/commit/1332f8ee0861a675fd524177bea4125594febecc))
* return cp-table refresh metadata ([97aaf63](https://github.com/LindemannRock/craft-redirect-manager/commit/97aaf63f734ef1ce173c3614425adca2f2de9096))


### Security

* block dangerous URL schemes in validation ([2316bbf](https://github.com/LindemannRock/craft-redirect-manager/commit/2316bbf0afca0b5dad013bf3cfe96019bdacf494))

## [5.32.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.32.0...v5.32.1) - 2026-06-07


### Fixed

* plugin credit in edit redirects ([2f750ca](https://github.com/LindemannRock/craft-redirect-manager/commit/2f750ca980048c7bec0be4b1dd21d1ace7918c7f))

## [5.32.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.31.0...v5.32.0) - 2026-06-07


### Added

* add act-static-analysis script for CI integration ([f4b99a9](https://github.com/LindemannRock/craft-redirect-manager/commit/f4b99a9a60c0e7affcc49c95bcf35daa80610626))
* add cache device detection option to settings ([bcc1fe9](https://github.com/LindemannRock/craft-redirect-manager/commit/bcc1fe9715a928619ecf4ef653a11b23891fda73))
* add optional backup volume UID for storing backups ([29ab65b](https://github.com/LindemannRock/craft-redirect-manager/commit/29ab65b6d86c19d070ba34ee2797f5535a5ee1ee))
* add permission checks for redirect statistics and analytics ([0aa8161](https://github.com/LindemannRock/craft-redirect-manager/commit/0aa816164f5e372702778bfe1b88da47c7713a66))
* add permission checks for redirects, analytics, and cache actions ([6a7d1a9](https://github.com/LindemannRock/craft-redirect-manager/commit/6a7d1a96069283cfa1651b19d7b0f9865133d126))
* add pluginHandle to device detection configuration ([7905993](https://github.com/LindemannRock/craft-redirect-manager/commit/7905993b49f1b7755c33eae4ee74dafe52ac9bf6))
* add redirect ID, status, match type, and creation type to edit view ([f8d2d22](https://github.com/LindemannRock/craft-redirect-manager/commit/f8d2d2276e5f6d23bb541ec2ab8c7df814d97e3f))
* add settings management link to navigation ([b4d05f6](https://github.com/LindemannRock/craft-redirect-manager/commit/b4d05f68585965588c57c6c50dc6029e28b091b8))
* **analytics:** add request type counting for analytics dashboard ([29982b4](https://github.com/LindemannRock/craft-redirect-manager/commit/29982b4d9de7addcbfa8f9e1f2b63371c4071310))
* **analytics:** add request type detection and storage in analytics records ([83641c8](https://github.com/LindemannRock/craft-redirect-manager/commit/83641c8645fd4288f02258458948bd8a7ff1ef11))
* **analytics:** add safe URL handling for recent unhandled 404s ([87d270c](https://github.com/LindemannRock/craft-redirect-manager/commit/87d270cb51178d76c55a15687d3934892079dd39))
* **analytics:** add safety checks for external URLs in most common 404s table ([7168201](https://github.com/LindemannRock/craft-redirect-manager/commit/7168201d0da4a42f48634eed87aa61077db633c2))
* **analytics:** add site-name mapping for analytics export ([3141395](https://github.com/LindemannRock/craft-redirect-manager/commit/3141395ae9b32cd52d9559671abf409308c825fa))
* **analytics:** add site-scope guard for deleting analytics records ([84924af](https://github.com/LindemannRock/craft-redirect-manager/commit/84924af74429c26c1302a995e21a12a89c5965de))
* **analytics:** add unique index for urlParsed and siteId in analytics table ([3f7e205](https://github.com/LindemannRock/craft-redirect-manager/commit/3f7e2057fe65a139829cfa823ec9a613d8cc6a2e))
* **analytics:** clamp analytics days parameter to valid range ([b83938b](https://github.com/LindemannRock/craft-redirect-manager/commit/b83938bd401b620427ad29c8a573242bdb29e867))
* **analytics:** require JSON acceptance for dashboard data actions ([6170ebc](https://github.com/LindemannRock/craft-redirect-manager/commit/6170ebc01e85670a830b5fda7aacbce52a729c3e))
* **analytics:** resolve redirect IDs for handled analytics in one query ([c40e506](https://github.com/LindemannRock/craft-redirect-manager/commit/c40e506ca768e5a8c58f535d514555793933b49a))
* **backup:** add volume storage support for backups and validation ([ced0fff](https://github.com/LindemannRock/craft-redirect-manager/commit/ced0fff4c3950bea165f11a0eca5730b5813992b))
* **backups:** add confirmation message for backup restore with redirect count ([62840b7](https://github.com/LindemannRock/craft-redirect-manager/commit/62840b75c37971031670e0dafabf7299184a416d))
* **cli:** add HelpController for cli command assistance ([18287be](https://github.com/LindemannRock/craft-redirect-manager/commit/18287beba02e2231f0df4703565407f935ebf0b6))
* **controllers:** enhance redirect deletion and update methods with record parameter ([452e72f](https://github.com/LindemannRock/craft-redirect-manager/commit/452e72f1f6edc00d9907b6a7a778280e10abcbf2))
* **controllers:** require JSON acceptance for delete and clear actions ([6307bf8](https://github.com/LindemannRock/craft-redirect-manager/commit/6307bf8493611b385b49adf17606570debd20e74))
* expand default date range options for analytics ([e250158](https://github.com/LindemannRock/craft-redirect-manager/commit/e25015882edb9e9d7291580b26cd198e08a267a3))
* **i18n:** add backup integrity check failure message ([5e4bfa6](https://github.com/LindemannRock/craft-redirect-manager/commit/5e4bfa67d9a019c5dfeaa6fb04f0eeab90ae0b08))
* **i18n:** add ID translation key in multiple locales ([8ffdf34](https://github.com/LindemannRock/craft-redirect-manager/commit/8ffdf34b7bf535d8d5bd61250cbdbd6f489ee7fc))
* **i18n:** add json_encode for redirect labels in stats chart ([241abd5](https://github.com/LindemannRock/craft-redirect-manager/commit/241abd579bc7808962019df1538d0be8c2a84ad4))
* **i18n:** add new maintenance and other translations ([84b3f20](https://github.com/LindemannRock/craft-redirect-manager/commit/84b3f2020113cf4f309d892bf5b39532fa851873))
* **i18n:** add new messages for backup actions and statuses ([e872c6e](https://github.com/LindemannRock/craft-redirect-manager/commit/e872c6ed08db0b57150a42dee6da932666670d79))
* **i18n:** add new redirect messages for multiple languages ([14d961f](https://github.com/LindemannRock/craft-redirect-manager/commit/14d961fd8c7a58c64333322a171c0c3dd62d00f9))
* **i18n:** add new redirect messages for multiple languages ([0c7c661](https://github.com/LindemannRock/craft-redirect-manager/commit/0c7c66101a37036f5dfc473dce4a4e74097e93e8))
* **i18n:** add new required field messages for CSV mapping ([92c5ab4](https://github.com/LindemannRock/craft-redirect-manager/commit/92c5ab4f18eb6b3fd9add283ac7581e32ec3fb58))
* **i18n:** add new translation keys for creation and update timestamps ([6906484](https://github.com/LindemannRock/craft-redirect-manager/commit/6906484f1202d45a840ba199b9f39c22c0ad32a4))
* **i18n:** add new translation keys for user notifications ([d0cf594](https://github.com/LindemannRock/craft-redirect-manager/commit/d0cf594c039d3c65bc203fa63a047478a9569e30))
* **i18n:** add query string handling and privacy level descriptions ([5ba4316](https://github.com/LindemannRock/craft-redirect-manager/commit/5ba431646ed6c7649abdaff7a0a1f88033446a6f))
* **i18n:** add regex and wildcard match instructions for redirects ([23df547](https://github.com/LindemannRock/craft-redirect-manager/commit/23df547cac3c6d4b7b0270e1cba92b1ac724844e))
* **i18n:** add translations for invalid data type and backup file format ([d408203](https://github.com/LindemannRock/craft-redirect-manager/commit/d4082033b9a3af4a3552a6935f8c0416b5a5c255))
* **i18n:** add validation and testing messages for redirect manager ([d9d3746](https://github.com/LindemannRock/craft-redirect-manager/commit/d9d374680773c95088ed7c575e6aebd29cd11523))
* **i18n:** update cache location messages for clarity and consistency ([6d957e1](https://github.com/LindemannRock/craft-redirect-manager/commit/6d957e106974a48ecec1f4818a89d81f200ead7a))
* **import-export:** add site permission check for redirect creation ([694d7dc](https://github.com/LindemannRock/craft-redirect-manager/commit/694d7dc63c60ea0891ce666506e1fed6ebd1eac2))
* **import-export:** add site-scope guard for redirect export queries ([bbac174](https://github.com/LindemannRock/craft-redirect-manager/commit/bbac17410c594758536b1618d7e4d61bd0ecb327))
* **import-export:** enhance backup handling for volume storage support ([be83ab9](https://github.com/LindemannRock/craft-redirect-manager/commit/be83ab943300c62ae2aaa28e788bd0f1576c2600))
* **import-export:** enhance import confirmation message with backup notice ([9dc7f6c](https://github.com/LindemannRock/craft-redirect-manager/commit/9dc7f6c26facd116fc0a6a82cd90e9d7250a393b))
* **import-export:** validate backup integrity before restore process ([ec2c7fd](https://github.com/LindemannRock/craft-redirect-manager/commit/ec2c7fdad4c0785039ae32cb8f6cc520524b74da))
* **jobs:** calculate next run time for CleanupAnalyticsJob ([1630310](https://github.com/LindemannRock/craft-redirect-manager/commit/16303100f358894d83a1327f9dbf5f90ba7d63af))
* **settings:** add backup path validation and resolve logic ([80032e7](https://github.com/LindemannRock/craft-redirect-manager/commit/80032e7c144d4511af0dde63c879f86b60fc13ad))
* **settings:** add backup schedule options and effective schedule method ([c48c3fb](https://github.com/LindemannRock/craft-redirect-manager/commit/c48c3fb7a7a777a8104f90de12770f75b8ae45f4))
* **settings:** add URL validation and improved result display for test redirects ([6c5e684](https://github.com/LindemannRock/craft-redirect-manager/commit/6c5e684e1a98cc5ee2d4fff13c1c9561b7dafb64))
* **settings:** validate backupPath and provide default storage path ([14e078d](https://github.com/LindemannRock/craft-redirect-manager/commit/14e078d1e92102e77d7ee8fea185079ea3e28133))
* **settings:** validate backupPath in settings controller ([fd7dbfc](https://github.com/LindemannRock/craft-redirect-manager/commit/fd7dbfcb2278daf4f519f776d85cac2d3eeed8d6))
* **tests:** add AnalyticsRequestTypeTest for request type classification ([a256403](https://github.com/LindemannRock/craft-redirect-manager/commit/a256403d66c3ae4877f1f1f4cd3b8c763e558572))
* **tests:** add integration tests for backup schedule and analytics request type ([7744247](https://github.com/LindemannRock/craft-redirect-manager/commit/77442478ab1d6c7c6f605bfd512b3a529a3fd6bb))
* **tests:** add SchedulerPatternTest for job rescheduling logic ([7169c42](https://github.com/LindemannRock/craft-redirect-manager/commit/7169c420ea17a3c9775750eb2ba5a71d510d9194))


### Fixed

* **analytics:** change permission requirement for clearAll action ([7366aa5](https://github.com/LindemannRock/craft-redirect-manager/commit/7366aa5a57e4eb5f7670b618da24c827b8cd3dae))
* **analytics:** convert lastHit dates to DateTime for timezone handling ([104cd92](https://github.com/LindemannRock/craft-redirect-manager/commit/104cd92b26873fc2d16913a7179ffcab5638e7db))
* **analytics:** convert lastHit to DateTime for timezone handling ([8880641](https://github.com/LindemannRock/craft-redirect-manager/commit/8880641c2f0258057e55b01a0314df6bc7e7a00f))
* **analytics:** correct error message for deleting analytics record ([91f6cb5](https://github.com/LindemannRock/craft-redirect-manager/commit/91f6cb5bf29a2e0dcefcee4f093e24f2ec299e98))
* **analytics:** redirect to analytics page on empty export data ([3ce1ed9](https://github.com/LindemannRock/craft-redirect-manager/commit/3ce1ed9a8839b3ee2a816d91414c0c1b03e0a6f5))
* **analytics:** replace date formatting with cascade style for consistency ([9d953a3](https://github.com/LindemannRock/craft-redirect-manager/commit/9d953a35e1734e3cd5198558ed6d7bf0ce479a0b))
* **analytics:** replace short date formatting with default for consistency ([2ce8d91](https://github.com/LindemannRock/craft-redirect-manager/commit/2ce8d91c867d449cea63cf8c9ca1f0774a815597))
* change backup schedule option from 'manual' to 'disabled' ([f128485](https://github.com/LindemannRock/craft-redirect-manager/commit/f128485c46b8bc9f18c08754b5e47b52a7647cb4))
* change backup schedule option from 'manual' to 'disabled' ([0f341b3](https://github.com/LindemannRock/craft-redirect-manager/commit/0f341b347b089f440d62975365cce90ba23ac0f6))
* change backup schedule option from 'manual' to 'disabled' ([e6bdeb1](https://github.com/LindemannRock/craft-redirect-manager/commit/e6bdeb1b5f7e1fc54c3c55c793ac5c48de5232ef))
* **controllers:** translate error messages in ImportExport and Redirects controllers ([ca80b3b](https://github.com/LindemannRock/craft-redirect-manager/commit/ca80b3b1a285be3fbeb57f5a565ce149e7e8df25))
* **controllers:** update backup schedule handling to reflect effective settings ([fed216b](https://github.com/LindemannRock/craft-redirect-manager/commit/fed216b34dfc5599824b50ce33ab49e297d117f2))
* correct error message for backup creation failure ([845f914](https://github.com/LindemannRock/craft-redirect-manager/commit/845f914c15c6d12960c7cf2f6aabf487230bf28a))
* correct error message for saving settings in SettingsController ([e89c557](https://github.com/LindemannRock/craft-redirect-manager/commit/e89c557568c4ffd5506d4321d62ed7ee77a2b929))
* **docs:** update backup schedule option from 'manual' to 'disabled' ([725ca36](https://github.com/LindemannRock/craft-redirect-manager/commit/725ca366d308d154cde2a882ca71c33a8db1496d))
* escape backup location label in settings template ([76b2819](https://github.com/LindemannRock/craft-redirect-manager/commit/76b28193d53f4ca41e42db5a7efffdcff3d0ecd3))
* **i18n:** correct German translations for analytics terms ([74d20f0](https://github.com/LindemannRock/craft-redirect-manager/commit/74d20f004d93a881815e3629d44446724fb14362))
* **i18n:** correct permission error messages in controllers and jobs ([b4a2494](https://github.com/LindemannRock/craft-redirect-manager/commit/b4a2494f7ed024b1e38b1ad132af0951d7b25520))
* **i18n:** correct phrasing in 404 tracking deletion confirmation ([3749370](https://github.com/LindemannRock/craft-redirect-manager/commit/3749370533e44b0c02d11ad2223988f66589e996))
* **i18n:** correct phrasing in device detection caching message ([b35f329](https://github.com/LindemannRock/craft-redirect-manager/commit/b35f3290631804d8195133a913bbd0f2448ad95d))
* **i18n:** correct Portuguese translations for backup and log terms ([1e5a7ee](https://github.com/LindemannRock/craft-redirect-manager/commit/1e5a7eebc7bf9f3ac3f50ec14109f4b626ce0279))
* **i18n:** correct Portuguese translations for operating system and browser terms ([93e2665](https://github.com/LindemannRock/craft-redirect-manager/commit/93e26653ed45793e45a2858a1e2dbb520e2d9341))
* **i18n:** correct pronouns in Swedish translation strings ([7513461](https://github.com/LindemannRock/craft-redirect-manager/commit/75134618b430ee4038f4c6e7da9fcae9bc4a1fa3))
* **i18n:** correct punctuation in Japanese translation strings ([301246d](https://github.com/LindemannRock/craft-redirect-manager/commit/301246d59db4ec49c74ede0e1dd51eb33447f4f7))
* **i18n:** correct Spanish translations for cache device detection ([90941ef](https://github.com/LindemannRock/craft-redirect-manager/commit/90941ef4143a2d65e30aa8d56fbaaa6842507854))
* **i18n:** remove import history strings ([5d0d7ec](https://github.com/LindemannRock/craft-redirect-manager/commit/5d0d7ecbf270791dd37df757ee4957e9822f6ff9))
* **i18n:** remove outdated 'Live' translation key ([1eaaed6](https://github.com/LindemannRock/craft-redirect-manager/commit/1eaaed676acd376235a0e508d4c92db1d1ea35f3))
* **i18n:** remove outdated translation keys for creation and update dates ([0c48ea5](https://github.com/LindemannRock/craft-redirect-manager/commit/0c48ea55f41714de2f52faa9c9c05d060910b4c8))
* **i18n:** remove scheduled initial analytics cleanup job translation ([95796ea](https://github.com/LindemannRock/craft-redirect-manager/commit/95796ea43da9b2a17c1558a37d20b74f1dd0a3d2))
* **i18n:** replace 'app' translations with 'redirect-manager' for consistency ([2acf901](https://github.com/LindemannRock/craft-redirect-manager/commit/2acf9014d40f0642c21abdcb13f5bf363a2d9278))
* **i18n:** translate 'All Sites' string in import preview ([b100ae3](https://github.com/LindemannRock/craft-redirect-manager/commit/b100ae3ff7db37ecdbfac2660876418e359fac5e))
* **i18n:** translate enabled status in import preview table ([fbdfe2f](https://github.com/LindemannRock/craft-redirect-manager/commit/fbdfe2f8776561b351bc505df29cb63c39c93a1f))
* **import-export:** replace view history permission check with manageImportExport permission ([9173c12](https://github.com/LindemannRock/craft-redirect-manager/commit/9173c1293c88111392835a8e2b15bfa39895d35b))
* **jobs:** update backup scheduling logic to use effective settings ([36c0113](https://github.com/LindemannRock/craft-redirect-manager/commit/36c01135fb6f708615667fbdf1f40bc93c4b85bc))
* **permissions:** remove viewImportHistory permission from plugin ([fcada4b](https://github.com/LindemannRock/craft-redirect-manager/commit/fcada4b157954765164f046af86c0438201fa5f4))
* **redirects:** replace 'Live' status with 'Enabled' for clarity ([259f427](https://github.com/LindemannRock/craft-redirect-manager/commit/259f427b3091af9cada35757f741980afb7a7073))
* **settings:** replace backup schedule options with effective settings ([092b398](https://github.com/LindemannRock/craft-redirect-manager/commit/092b3984b4e7f8d8ec0a624da488868e4f639990))

## [5.31.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.30.0...v5.31.0) - 2026-05-21


### Added

* add pre-commit hook for ECS and PHPStan code quality checks ([5534955](https://github.com/LindemannRock/craft-redirect-manager/commit/55349552585b837ab551ce410d088f0e3f9603ab))
* **analytics:** add log category to geo configuration ([e44b978](https://github.com/LindemannRock/craft-redirect-manager/commit/e44b9787397b3fe695ce020887616ffbfd42ac56))
* **dashboard:** enhance 404 analytics dashboard with improved action handling and user permissions ([6b52b6a](https://github.com/LindemannRock/craft-redirect-manager/commit/6b52b6a0b2b876db5996db3811674dd708fde6ba))
* **geo:** integrate GeoSettingsTrait into Settings model ([ddabfe5](https://github.com/LindemannRock/craft-redirect-manager/commit/ddabfe51269d5efc1f56d769f7e4edc2eae7591e))
* **i18n:** add new analytics and redirect messages in multiple languages ([fc75239](https://github.com/LindemannRock/craft-redirect-manager/commit/fc75239f3f322c03d786aca6c9a8300edeaeff43))
* **i18n:** add translation issue template for reporting language problems ([c1bbb4f](https://github.com/LindemannRock/craft-redirect-manager/commit/c1bbb4fa25cfcb6a476242a1a1e0831d35b58a9c))
* **i18n:** remove deprecated error messages from translation files ([c353738](https://github.com/LindemannRock/craft-redirect-manager/commit/c353738aa4de608e470524809822570f12af56f9))
* **i18n:** remove plugin name translations from multiple locales ([cd10888](https://github.com/LindemannRock/craft-redirect-manager/commit/cd108881a7cada5951f4c61013c20b9d3b91e2ae))
* **redirects:** improve redirects listing with enhanced action handling and bulk operations ([6b52b6a](https://github.com/LindemannRock/craft-redirect-manager/commit/6b52b6a0b2b876db5996db3811674dd708fde6ba))
* **settings:** add attribute labels for redirect and analytics settings ([a71db1c](https://github.com/LindemannRock/craft-redirect-manager/commit/a71db1cbb78da7b96cf5d7b7a180fe81089c0bdd))
* **settings:** add new configuration options for date and export formats ([d98061c](https://github.com/LindemannRock/craft-redirect-manager/commit/d98061c455b180e389cf64246bf379761074256c))
* **settings:** handle multi-state selects and add new interface options ([0e1f329](https://github.com/LindemannRock/craft-redirect-manager/commit/0e1f3296e8b8d6d2a22d2b909d46d8bcc8cdf09c))
* **tests:** add integration tests for analytics and redirects functionality ([3e10a9c](https://github.com/LindemannRock/craft-redirect-manager/commit/3e10a9cce37ea3c22e55a288ac60b01ed51f0b19))


### Fixed

* correct ellipsis in redirect source URL display ([37942b0](https://github.com/LindemannRock/craft-redirect-manager/commit/37942b05d97e854cc648e96c9e0a042cf5a25337))
* correct phpstan configuration path for Craft CMS integration ([2f6316d](https://github.com/LindemannRock/craft-redirect-manager/commit/2f6316d1eeb8d609e69b0cd7058ecf5bab328da7))
* **i18n:** align 54 cross-plugin shared translations across 12 languages ([978f972](https://github.com/LindemannRock/craft-redirect-manager/commit/978f9720c0460d240226cf533577b169978f6ecb))
* **i18n:** correct loading message translations in multiple languages ([4a6f41b](https://github.com/LindemannRock/craft-redirect-manager/commit/4a6f41b53de4f4bfda7dd360840b0631fe631216))
* **i18n:** correct punctuation in device detection caching message ([34cb202](https://github.com/LindemannRock/craft-redirect-manager/commit/34cb2027a3270d91a0874af727e6e9348ddc1ba2))

## [5.30.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.29.0...v5.30.0) - 2026-05-06


### Features

* **translations:** remove geo provider options from multiple locales ([518100e](https://github.com/LindemannRock/craft-redirect-manager/commit/518100eac72efbea03b6ec4b6541ffd74e24506c))


### Bug Fixes

* apply config overrides through shared settings helper ([77e246c](https://github.com/LindemannRock/craft-redirect-manager/commit/77e246cd5e22f2e418fec3f6490e6f68d6fdf2a6))
* drop PAT requirement for release-please — use built-in GITHUB_TOKEN ([78b54aa](https://github.com/LindemannRock/craft-redirect-manager/commit/78b54aa39796aa6af5f57a80ff25d6ca8987e073))
* update geo-settings to use plugin handle instead of translation category ([613b164](https://github.com/LindemannRock/craft-redirect-manager/commit/613b164a12a3527b0760e5f48bafd942bb406695))


### Miscellaneous Chores

* update version annotations to 5.24.0 and 5.25.0 ([9e37253](https://github.com/LindemannRock/craft-redirect-manager/commit/9e372534b32d653c1568994240f8f9db012bea46))

## [5.29.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.2...v5.29.0) - 2026-04-05


### Features

* Add 10 new language translations (FR, NL, ES, AR, IT, PT, JA, SV, DA, NO) ([2532350](https://github.com/LindemannRock/craft-redirect-manager/commit/253235024e24901ab072c53964d74f0bd9552b17))


### Bug Fixes

* read-only settings and response handling ([cbee99b](https://github.com/LindemannRock/craft-redirect-manager/commit/cbee99be438aa5e565fb59f5f3eb7d6befa0b2b5))
* translate install experience text to Craft CMS ([6d82eb6](https://github.com/LindemannRock/craft-redirect-manager/commit/6d82eb63bda1b75358c84f76ecaaa9de90f798d5))

## [5.28.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.1...v5.28.2) - 2026-03-18


### Bug Fixes

* **config:** clarify log level options comment ([4e7d405](https://github.com/LindemannRock/craft-redirect-manager/commit/4e7d405848085aeef1191269ab47e4427ac0b487))

## [5.28.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.28.0...v5.28.1) - 2026-03-17


### Miscellaneous Chores

* **workflow:** update release-please.yml permissions and settings ([33bd4f4](https://github.com/LindemannRock/craft-redirect-manager/commit/33bd4f434d46f57517bfb1d67e0cf7c53fc6483c))

## [5.28.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.27.0...v5.28.0) - 2026-03-17


### Features

* **analytics:** streamline IP processing and remove redundancy ([88ba22c](https://github.com/LindemannRock/craft-redirect-manager/commit/88ba22c94b7978e011519caa455a8c423bdd78ad))

## [5.27.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.26.0...v5.27.0) - 2026-03-17


### Features

* **analytics:** implement build process and update asset management ([36d19eb](https://github.com/LindemannRock/craft-redirect-manager/commit/36d19ebe4f6fc45820bdaa48821ef8bd2948d26b))
* **redirect-manager:** add install experience configuration ([9c4344e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c4344ef16aaa8ada251457d6dc4904b1ef83367))


### Bug Fixes

* **import-export:** remove unused menu button initialization code ([382c9a0](https://github.com/LindemannRock/craft-redirect-manager/commit/382c9a00c06c40640cb870729d5a652baa81624a))
* **settings:** remove redundant submit button from settings forms ([9dd2ff2](https://github.com/LindemannRock/craft-redirect-manager/commit/9dd2ff2c44680b613b6d748931c1d15e1e83ae95))

## [5.26.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.25.0...v5.26.0) - 2026-03-04


### Features

* add complete EN/DE translation ([d534845](https://github.com/LindemannRock/craft-redirect-manager/commit/d534845a9def0b94782208cde21824bd5c10f405))


### Bug Fixes

* **jobs:** implement RetryableJobInterface in job classes ([1f348a5](https://github.com/LindemannRock/craft-redirect-manager/commit/1f348a534d74cb5f4d1fef11886ed14ebafd2538))
* **settings:** validate integer settings and improve error handling ([495c806](https://github.com/LindemannRock/craft-redirect-manager/commit/495c806d7b107bea451af7195f44b9d4c8228aec))

## [5.25.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.24.1...v5.25.0) - 2026-02-24


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

## [5.24.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.24.0...v5.24.1) - 2026-02-07


### Bug Fixes

* **ImportExportController, AnalyticsService:** replace DateTimeHelper with DateFormatHelper for date formatting ([a4a78fa](https://github.com/LindemannRock/craft-redirect-manager/commit/a4a78fac4ef9cfb3f348537bf45c1ee37b8419af))

## [5.24.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.23.0...v5.24.0) - 2026-02-05


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

## [5.23.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.22.0...v5.23.0) - 2026-01-28


### Features

* enhance redirects listing page and improve dashboard settings ([51d56f1](https://github.com/LindemannRock/craft-redirect-manager/commit/51d56f1b2b051de6464b9c2e80b5247d08d38034))

## [5.22.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.21.0...v5.22.0) - 2026-01-26


### Features

* replace direct plugin access with PluginHelper methods ([9ec3fc5](https://github.com/LindemannRock/craft-redirect-manager/commit/9ec3fc543d82711d5447a714951a412aad796c09))


### Bug Fixes

* **jobs:** prevent duplicate scheduling of CleanupAnalyticsJob ([c1c3ea5](https://github.com/LindemannRock/craft-redirect-manager/commit/c1c3ea53fffd6ef70d5e0a5ae61241ba68af1962))
* **security:** address XSS, permissions, cache, and CSV injection vulnerabilities ([1d42686](https://github.com/LindemannRock/craft-redirect-manager/commit/1d426868fb1b34a0173651cbd61b84e2dd7fb40d))
* **security:** validate URL schemes to prevent unsafe links in dashboard ([25a897f](https://github.com/LindemannRock/craft-redirect-manager/commit/25a897fe7645afd05045cfe67a3b7e8e5be189e6))

## [5.21.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.20.0...v5.21.0) - 2026-01-21


### Features

* Add configurable geo IP provider settings with HTTPS support ([633cc5d](https://github.com/LindemannRock/craft-redirect-manager/commit/633cc5d8b937f798c98bf82f890b63358e5e811a))
* Enhance URL validation and error reporting in import/export process ([38f879c](https://github.com/LindemannRock/craft-redirect-manager/commit/38f879c424f23b22eafa06badd3bf8fe43d48fd2))
* Refactor backup and error messaging components in import/export templates ([2a7c903](https://github.com/LindemannRock/craft-redirect-manager/commit/2a7c9038068dcdcb6ff081b145eaee174172285a))


### Bug Fixes

* **security:** address multiple security vulnerabilities ([2d97c82](https://github.com/LindemannRock/craft-redirect-manager/commit/2d97c82e41a28f9496d07bb79ee6b97b1a0bf380))
* swap cache and backup settings links in the sidebar ([bb1b036](https://github.com/LindemannRock/craft-redirect-manager/commit/bb1b0362e40f6c77640a079ff9c4fac1286c79a6))

## [5.20.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.19.0...v5.20.0) - 2026-01-16


### Features

* add site options to redirect edit form and improve template structure ([c1acf3d](https://github.com/LindemannRock/craft-redirect-manager/commit/c1acf3dd56478451a970dde8d7c737f7ec43482c))


### Bug Fixes

* reorganize and standardize analytics templates ([b493e43](https://github.com/LindemannRock/craft-redirect-manager/commit/b493e43ce6dcf0187a1df7637fa2a9479771da06))
* update button label to clarify saving settings ([7f521e0](https://github.com/LindemannRock/craft-redirect-manager/commit/7f521e0b49ea03970cae92aab63f64ff27207194))
* update cache location message to use redirectHelper for dynamic path ([7e63e0e](https://github.com/LindemannRock/craft-redirect-manager/commit/7e63e0ee4c4f1c70724e898ea87d1935561c96c0))
* update filename generation and improve CSV import handling ([f619962](https://github.com/LindemannRock/craft-redirect-manager/commit/f6199622f8bec59f0efd2abae497f4b241700ece))
* update hardcoded cache paths with PluginHelper for consistency ([36cb9e0](https://github.com/LindemannRock/craft-redirect-manager/commit/36cb9e0ba9af30f81b6cbab91174620709af304d))
* update PluginHelper bootstrap to include download permissions for logging ([e6fa6b0](https://github.com/LindemannRock/craft-redirect-manager/commit/e6fa6b0eef0def38849ab7a11f01d92a5602fdda))

## [5.19.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.2...v5.19.0) - 2026-01-12


### Features

* add analytics count retrieval to RedirectManagerUtility ([c8204ca](https://github.com/LindemannRock/craft-redirect-manager/commit/c8204ca60264a8dcea96cce8a75bd15f734327c3))


### Bug Fixes

* format cache file counts and update analytics button style ([4b4fd9b](https://github.com/LindemannRock/craft-redirect-manager/commit/4b4fd9b8edf0c3c1e02cb8b7b1f8be89bbb36301))
* update icon path for Unhandled404sWidget ([ab06edd](https://github.com/LindemannRock/craft-redirect-manager/commit/ab06edddaa63062addb169ab49b8630475d3378a))

## [5.18.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.1...v5.18.2) - 2026-01-12


### Bug Fixes

* remove redundant redirects and analytics display limits from settings and config ([48f13d9](https://github.com/LindemannRock/craft-redirect-manager/commit/48f13d9015a53cdc5dc56ae1ff15b948ecaf6c8a))

## [5.18.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.18.0...v5.18.1) - 2026-01-11


### Bug Fixes

* plugin name retrieval to use getFullName method for consistency ([d410744](https://github.com/LindemannRock/craft-redirect-manager/commit/d41074406dffb930faec9331666c9ba87f2ee8e1))

## [5.18.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.17.0...v5.18.0) - 2026-01-10


### Features

* Add redirect analytics functionality with new template and service methods ([303faad](https://github.com/LindemannRock/craft-redirect-manager/commit/303faade49718b84d5c2c7221ef6aef527584bac))

## [5.17.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.16.0...v5.17.0) - 2026-01-09


### Features

* Update backup path structure to include 'imports' subdirectory for better organization ([42e7604](https://github.com/LindemannRock/craft-redirect-manager/commit/42e7604cab2f40d6f36ed155a09fb3bbf9cb402f))

## [5.16.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.15.0...v5.16.0) - 2026-01-09


### Features

* Enhance import/export functionality with improved URL parsing and auto-detection of creation type ([c5f8b20](https://github.com/LindemannRock/craft-redirect-manager/commit/c5f8b202ec5928353c04f51c0c5e0767034d326b))
* Update redirect creation to return new ID on success and enhance edit template with save options ([7c91fe3](https://github.com/LindemannRock/craft-redirect-manager/commit/7c91fe3abff9fb963e70055baa9a52b065cda18e))

## [5.15.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.4...v5.15.0) - 2026-01-09


### Features

* Enhance redirect matching to return all matches with resolved destinations ([53ca0da](https://github.com/LindemannRock/craft-redirect-manager/commit/53ca0da84cf8571253a43a9d8da8fd973a3ea5c7))


### Bug Fixes

* Update filename generation to use 'alltime' instead of 'all' for clarity ([b2aa0ab](https://github.com/LindemannRock/craft-redirect-manager/commit/b2aa0ab93cbb566bf394c9f115acde71e0f0b7d4))

## [5.14.4](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.3...v5.14.4) - 2026-01-08


### Bug Fixes

* Update search clear button selector in dashboard and redirects templates ([ce01cf1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce01cf1c9a49561f28bf815489624764a51f2e48))

## [5.14.3](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.2...v5.14.3) - 2026-01-08


### Bug Fixes

* Improve AJAX request handling in dashboard refresh function ([5ae6a55](https://github.com/LindemannRock/craft-redirect-manager/commit/5ae6a5554e506da8dfe912cb2f2530c1df64a0b8))

## [5.14.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.1...v5.14.2) - 2026-01-08


### Bug Fixes

* Preserve user input in search parameter for URL params ([d61e117](https://github.com/LindemannRock/craft-redirect-manager/commit/d61e1175b17aa94b4a1a61bb94cfeb01301a2162))

## [5.14.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.14.0...v5.14.1) - 2026-01-08


### Bug Fixes

* Update form action URL in dashboard template ([3c94242](https://github.com/LindemannRock/craft-redirect-manager/commit/3c942428e7583595d6c4b771e31851453dd88602))

## [5.14.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.13.0...v5.14.0) - 2026-01-08


### Features

* Add exportAnalytics permission and fix dashboard permissions ([843b4a1](https://github.com/LindemannRock/craft-redirect-manager/commit/843b4a1ad46eb227e08d0549f8574a949c136ed1))
* Add granular permissions and dynamic naming to redirect-manager ([60303af](https://github.com/LindemannRock/craft-redirect-manager/commit/60303af8c41b038a3922f753aa9f0cc7228b5a22))
* Simplify user permissions by grouping redirect management actions ([9230f3a](https://github.com/LindemannRock/craft-redirect-manager/commit/9230f3aa89e4023c8b4982e17a1c7625372e80c6))


### Bug Fixes

* update success message for settings save action ([7d3dc52](https://github.com/LindemannRock/craft-redirect-manager/commit/7d3dc529531b939d50f27537433f438c7ccd964d))

## [5.13.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.12.0...v5.13.0) - 2026-01-05


### Features

* migrate to lindemannrock/craft-plugin-base ([cf63978](https://github.com/LindemannRock/craft-redirect-manager/commit/cf6397815862f5c48746dc6fa6dbfb22ce9d2bbd))

## [5.12.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.11.0...v5.12.0) - 2026-01-05


### Features

* enhance analytics and redirects controllers with additional fields and improve dashboard UI ([90bad19](https://github.com/LindemannRock/craft-redirect-manager/commit/90bad19252f43b83b65a0147a43772a1b573d9ec))
* enhance dashboard and redirects UI with additional color coding for request and match types ([9c5bf9e](https://github.com/LindemannRock/craft-redirect-manager/commit/9c5bf9e224351eeda533d725811b26359aec05e8))

## [5.11.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.10.0...v5.11.0) - 2026-01-05


### Features

* implement AJAX-based dashboard data retrieval and auto-refresh functionality ([a64a83f](https://github.com/LindemannRock/craft-redirect-manager/commit/a64a83fae422fb6661aa630910494e6d0e64622a))
* **redirect-manager:** dashboard UI improvements and bug fixes ([dec1968](https://github.com/LindemannRock/craft-redirect-manager/commit/dec196864b9152c68873030a20f1e6cb9b56d8bc))

## [5.10.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.9.0...v5.10.0) - 2026-01-04


### Features

* add regex capture group support ($1, $2, etc.) for redirect destinations ([56743b8](https://github.com/LindemannRock/craft-redirect-manager/commit/56743b890bd4608d9e1badf1094719343503197a))

## [5.9.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.2...v5.9.0) - 2025-12-19


### Features

* Add geographic analytics and traffic analysis tabs with detailed statistics ([1e986e3](https://github.com/LindemannRock/craft-redirect-manager/commit/1e986e33745b18b1fb1fe3575e71cc98f71a548d))
* add geographic analytics for top countries and cities ([de5b98f](https://github.com/LindemannRock/craft-redirect-manager/commit/de5b98f5c3ef7ef6a93b0c106895ad8e55c1da39))


### Bug Fixes

* improve display name handling and trim whitespace in settings ([c6aee51](https://github.com/LindemannRock/craft-redirect-manager/commit/c6aee5148434e819d77f7c7a73f90a54e2ae91d1))
* update cache duration fields and improve instructions ([25be9a6](https://github.com/LindemannRock/craft-redirect-manager/commit/25be9a6e1b0dec7b9a7b634ed9661dac4d5f138f))

## [5.8.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.1...v5.8.2) - 2025-12-16


### Bug Fixes

* update icon for Redirect Manager Utility ([ba08098](https://github.com/LindemannRock/craft-redirect-manager/commit/ba08098a203d411f629a103442261976728e3380))

## [5.8.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.8.0...v5.8.1) - 2025-12-16


### Bug Fixes

* update time formatting in analytics dashboard to use locale settings ([3e79ff4](https://github.com/LindemannRock/craft-redirect-manager/commit/3e79ff417e0cf526ca210c246d262c88d0de80a3))

## [5.8.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.7.0...v5.8.0) - 2025-12-16


### Features

* add cache storage method configuration for different environments ([287fa40](https://github.com/LindemannRock/craft-redirect-manager/commit/287fa40364851c136068679d313fdefae82ccd92))
* add cache storage method configuration to settings table ([6b9e424](https://github.com/LindemannRock/craft-redirect-manager/commit/6b9e424c192355548251f65561343e7e9d575d89))
* add SVG icon for redirect manager ([33af8ee](https://github.com/LindemannRock/craft-redirect-manager/commit/33af8eee101beb1912c1e1b816591d34bb2ecf24))
* enhance analytics data handling and timezone conversion; improve dashboard pagination links ([8e72a18](https://github.com/LindemannRock/craft-redirect-manager/commit/8e72a189eeb5188a20de6e02b7f1304fb95d659d))
* enhance cache status display and button functionality based on storage method ([e2ed5dc](https://github.com/LindemannRock/craft-redirect-manager/commit/e2ed5dcec1f96f568ba72168acb43a0df66afef6))
* implement cache storage method configuration and handling for Redis and file systems ([6b6058d](https://github.com/LindemannRock/craft-redirect-manager/commit/6b6058d5bd0f5f6fd37b978ebf77e8559bcdfd3c))
* update smart caching description and add cache storage method configuration ([461b806](https://github.com/LindemannRock/craft-redirect-manager/commit/461b806440c26eaa83c6c9932f22785632ed7f0d))

## [5.7.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.6.0...v5.7.0) - 2025-12-03


### Features

* add PHPStan and EasyCodingStandard configurations; enhance code quality checks ([0727817](https://github.com/LindemannRock/craft-redirect-manager/commit/0727817203d8620d0830c4eecf8857584b1868ef))
* **analytics:** enhance date range filtering and export functionality ([e15086f](https://github.com/LindemannRock/craft-redirect-manager/commit/e15086fa170af759b99dd1b91e1f0691ad19cefd))
* **info-box:** add new Info Box component for displaying informational notices ([353777e](https://github.com/LindemannRock/craft-redirect-manager/commit/353777e962289b40a090e6d0ec1720e46f99b7ce))
* **settings:** add default country and city for local development ([ad34d13](https://github.com/LindemannRock/craft-redirect-manager/commit/ad34d1331b29bb8059759c234567a706d4395c55))


### Bug Fixes

* **settings:** clarify device detection cache duration comment ([7e9477d](https://github.com/LindemannRock/craft-redirect-manager/commit/7e9477dfd6f43379755afcb7a0cd923e0e13c8c9))


### Miscellaneous Chores

* update version annotations to reflect new versioning scheme across multiple files ([891ebe8](https://github.com/LindemannRock/craft-redirect-manager/commit/891ebe84a4deb5c35619532686413fa5edb25b29))

## [5.6.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.5.0...v5.6.0) - 2025-11-15


### Features

* **license:** add MIT License file to the repository ([649ff9e](https://github.com/LindemannRock/craft-redirect-manager/commit/649ff9e28aae8d3310d670653202688c26fc8c8d))


### Bug Fixes

* **backup:** adjust margin style for backup settings header ([30c58fa](https://github.com/LindemannRock/craft-redirect-manager/commit/30c58fadbaa15831582593106e716fc88db9db27))

## [5.5.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.4.0...v5.5.0) - 2025-11-14


### Features

* **plugin:** enhance plugin name handling and introduce Twig extension for name variations ([a73917f](https://github.com/LindemannRock/craft-redirect-manager/commit/a73917f31972adc31f34546338ce8049e3492c98))
* **settings:** allow disabling undo window and improve site handling in redirects ([53903de](https://github.com/LindemannRock/craft-redirect-manager/commit/53903de93e8c8a7d2007a0e960024104b1b4a40d))

## [5.4.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.2...v5.4.0) - 2025-11-11


### Features

* **settings:** add WordPress migration filters and update settings description ([ce424e1](https://github.com/LindemannRock/craft-redirect-manager/commit/ce424e193c89e73627feaa287a7dd38416b5a4d4))


### Bug Fixes

* **advanced-settings:** improve descriptions for quick setup and WordPress migration filters ([3f002c0](https://github.com/LindemannRock/craft-redirect-manager/commit/3f002c087474d2f5cfe83978dcd6d194a83db501))

## [5.3.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.1...v5.3.2) - 2025-11-11


### Bug Fixes

* **ip-salt-error:** enhance error message with copyable commands for generating IP hash salt ([1822b66](https://github.com/LindemannRock/craft-redirect-manager/commit/1822b66d5037a4f19ffc39ce18e560439c147414))

## [5.3.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.3.0...v5.3.1) - 2025-11-07


### Bug Fixes

* CleanupAnalyticsJob with next run time calculation and display ([1d489ce](https://github.com/LindemannRock/craft-redirect-manager/commit/1d489ce4308f784f4ba268583361dc102575cf4e))

## [5.3.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.2.0...v5.3.0) - 2025-11-07


### Features

* add backup path and volume UID to settings table; remove import history table ([33eb038](https://github.com/LindemannRock/craft-redirect-manager/commit/33eb038d57d299fd714850a712f1ad0fb29addd1))
* Centralize redirect integration with undo detection and notifications ([e9b3d95](https://github.com/LindemannRock/craft-redirect-manager/commit/e9b3d95814b8b0be27f333ea50234bbf87249c50))


### Bug Fixes

* dynamically retrieve plugin names for better source plugin display ([87b46b3](https://github.com/LindemannRock/craft-redirect-manager/commit/87b46b363db3348802e7550f0bf4ec42ec3f4aea))
* enhance analytics CSV export with additional fields and improved layout ([9a6982d](https://github.com/LindemannRock/craft-redirect-manager/commit/9a6982d3a448a8e196f5b208ee947ada720da5a7))
* improve backup notification logic in import preview template ([5908836](https://github.com/LindemannRock/craft-redirect-manager/commit/5908836bc4052d19b8ba30ea76bc12ed50e71f3d))
* remove unused getBackupHistory method from RedirectManagerVariable ([3abaebc](https://github.com/LindemannRock/craft-redirect-manager/commit/3abaebca36eee520ff700ba08f447d90db0b6be0))

## [5.2.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.3...v5.2.0) - 2025-11-03


### Features

* add setter method support and enhance config override checks for settings ([f1c6f1c](https://github.com/LindemannRock/craft-redirect-manager/commit/f1c6f1c730eb5b41ca229c4999c591cf49c585b8))


### Bug Fixes

* enhance backup creation logic to check for existing redirects and update template conditions ([1c5400f](https://github.com/LindemannRock/craft-redirect-manager/commit/1c5400f58f5c471b1b57f93aa026e1a542100d7a))
* logging documentation and update analytics handling in Redirect Manager ([f89928e](https://github.com/LindemannRock/craft-redirect-manager/commit/f89928eea793f033c636506f108e1c8d326ed42a))
* remove unnecessary blank line in import/export template ([485f8d2](https://github.com/LindemannRock/craft-redirect-manager/commit/485f8d21d4887ba488c5c6fee0a9efba46cb1a92))

## [5.1.3](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.2...v5.1.3) - 2025-10-26


### Bug Fixes

* enhance log level validation to ensure 'debug' is only allowed in devMode ([fc96a36](https://github.com/LindemannRock/craft-redirect-manager/commit/fc96a3683e5bee86937ffafa3da94f4e44847ce3))

## [5.1.2](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.1...v5.1.2) - 2025-10-26


### Bug Fixes

* reorganize config settings for clarity and add new logging and caching options ([1a2bf44](https://github.com/LindemannRock/craft-redirect-manager/commit/1a2bf4451188bfb710f285970e6bc2075574e880))

## [5.1.1](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.1.0...v5.1.1) - 2025-10-26


### Bug Fixes

* add version to composer.json to fix release-please ([1d290d1](https://github.com/LindemannRock/craft-redirect-manager/commit/1d290d147b61aac04dff1cc2112111f215757b3d))

## [5.1.0](https://github.com/LindemannRock/craft-redirect-manager/compare/v5.0.0...v5.1.0) - 2025-10-26


### Features

* add analytics system, dashboard widgets, device detection, and IP privacy ([f428a1d](https://github.com/LindemannRock/craft-redirect-manager/commit/f428a1d8d350790a51b5acbce665d5f3bb324d38))
* add tooltip for custom headers in no-cache settings ([f0bfcab](https://github.com/LindemannRock/craft-redirect-manager/commit/f0bfcabee48c02084171957d8b36260b61e6d9ce))
* add utility page with system monitoring and cache management ([6e78b12](https://github.com/LindemannRock/craft-redirect-manager/commit/6e78b12bbd3ff9fb9d3b6da4aff0abd520900e6b))
* enhance templates to use dynamic plugin name for titles and labels ([2629559](https://github.com/LindemannRock/craft-redirect-manager/commit/2629559494a4f9ff2a893c43d44da8a554b0dc22))
* implement logging improvements across controllers and services ([0f5b463](https://github.com/LindemannRock/craft-redirect-manager/commit/0f5b4632fb797325b29d74350bac44aead7ce68f))


### Bug Fixes

* analytics handling in RedirectManager ([699185a](https://github.com/LindemannRock/craft-redirect-manager/commit/699185acdb70bb6fd2d7ec863b5bb9f7fd395afe))

## 5.0.0 - 2025-10-21


### Features

* initial Redirect Manager plugin implementation ([153c2ab](https://github.com/LindemannRock/craft-redirect-manager/commit/153c2aba744c196d8b7ea445c329bb87b179b664))
