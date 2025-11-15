# Changelog

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
