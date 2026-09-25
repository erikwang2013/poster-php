# poster-php 多语言文档 / Multi-language Documentation

[中文](../../README.md) | [English](../../README_EN.md) | [日本語](ja/README.md) | [한국어](ko/README.md) | [Русский](ru/README.md) | [Deutsch](de/README.md) | [Français](fr/README.md) | [Español](es/README.md) | [Português](pt/README.md) | [हिन्दी](hi/README.md) | [العربية](ar/README.md) | [বাংলা](bn/README.md) | [Bahasa Indonesia](id/README.md)

每个语言目录包含该语言的 `README.md` 与三张本地化图表（架构设计 / 功能设计 / 生命周期），
图表文案与生成方式见 [`scripts/i18n-diagrams.php`](../../scripts/i18n-diagrams.php)。

| 语言 | Language | 文档 | 图表 |
|------|----------|------|------|
| 中文（简体） | Chinese | [README.md](../../README.md) | [docs/](../) |
| English | English | [README_EN.md](../../README_EN.md) | [docs/i18n/en](en/) |
| 日本語 | Japanese | [ja/README.md](ja/README.md) | [ja/](ja/) |
| 한국어 | Korean | [ko/README.md](ko/README.md) | [ko/](ko/) |
| Русский | Russian | [ru/README.md](ru/README.md) | [ru/](ru/) |
| Deutsch | German | [de/README.md](de/README.md) | [de/](de/) |
| Français | French | [fr/README.md](fr/README.md) | [fr/](fr/) |
| Español | Spanish | [es/README.md](es/README.md) | [es/](es/) |
| Português | Portuguese | [pt/README.md](pt/README.md) | [pt/](pt/) |
| हिन्दी | Hindi | [hi/README.md](hi/README.md) | [hi/](hi/) |
| العربية | Arabic | [ar/README.md](ar/README.md) | [ar/](ar/) |
| বাংলা | Bengali | [bn/README.md](bn/README.md) | [bn/](bn/) |
| Bahasa Indonesia | Indonesian | [id/README.md](id/README.md) | [id/](id/) |

## 维护说明 / Maintaining

1. 改中文 `README.md`（权威版本）与 `README_EN.md`。
2. 同步各语言 `docs/i18n/{locale}/README.md`：**代码块保持与源文件一致**（只翻译注释），标题层级、表格、代码块数量不要增减。
3. 图表文案在 `scripts/i18n/{locale}.json`，改完执行 `php scripts/i18n-diagrams.php {locale}` 重新生成（`en.json` 是基准，缺键自动回退）。
