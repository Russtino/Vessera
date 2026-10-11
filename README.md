<p align="center"><img src="assets/icon-128x128.png" alt="" width="96" height="96"></p>

# Catalogist

A free WordPress plugin that turns a college website into a program and course catalog. Built for community and technical colleges.

**[Get it on WordPress.org](https://wordpress.org/plugins/catalogist/)** ·
**[Website](https://russtino.github.io/Catalogist/)** ·
**[Getting-started guide](https://russtino.github.io/Catalogist/getting-started/)** ·
**[Support forum](https://wordpress.org/support/plugin/catalogist/)**

![A program page with credential details and a semester-by-semester map](assets/screenshot-1.png)

## What it does

- **Program pages**: each credential a program offers, with its length, credits and a semester-by-semester map
- **Pathways**: routes students choose between within a credential, with a switcher and per-pathway credit totals
- **Choice slots**: electives and general education choices, including lecture and lab pairs
- **Map templates**: build general education requirements once and apply them to any program
- **Course pages**: code, credits, hours, description and linked prerequisites
- **Program Finder and Course Finder**: searchable, filterable lists for students
- **Spreadsheet import**: bring courses in from CSV or Excel, with a preview before anything is saved

Everything is entered once and stays in sync: change a course's credits, and every map that includes it updates.

## Requirements

- WordPress 6.6 or newer
- PHP 7.4 or newer
- Any block or classic theme

## Installing

In WordPress, go to **Plugins → Add New**, search for **Catalogist**, then click **Install Now** and **Activate**. Then follow the [getting-started guide](https://russtino.github.io/Catalogist/getting-started/). Updates arrive through WordPress's normal **Update now** link.

To try it with sample data, import the [Harmon Ridge sample catalog](downloads/harmon-ridge-catalog.xlsx), a fictional college's courses, programs and maps.

## Questions and problems

- **Questions about using Catalogist:** the [support forum](https://wordpress.org/support/plugin/catalogist/) on WordPress.org.
- **Bugs and feature ideas:** [open an issue](https://github.com/russtino/Catalogist/issues) with your WordPress version, your theme, and the steps that lead to the problem.

## Repository layout

```
catalogist/           The plugin (the same code as on WordPress.org)
index.html            The project website (GitHub Pages)
getting-started/      The getting-started guide
assets/               Website images: icon, banner and screenshots
downloads/            The sample catalog spreadsheet
```

## Development

The plugin's PHP needs no build step. The editor and import screens are built with [`@wordpress/scripts`](https://www.npmjs.com/package/@wordpress/scripts), so changing anything in `catalogist/src/` requires Node.js. From the `catalogist` folder:

```sh
npm install      # once
npm start        # rebuilds while you work
npm run build    # production build into build/
```

The compiled `build/` folder is committed so the plugin works straight from the repository.

### Releasing a new version

1. Bump the version in `catalogist.php` (header and `CATALOGIST_VERSION`), `package.json`, the `blocks/*/block.json` files and the **Stable tag** in `readme.txt`, and add a changelog entry to `readme.txt`.
2. Run the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin on the result.
3. Copy the plugin into the WordPress.org SVN repository's `trunk`, copy `trunk` to a new folder under `tags` named for the version, and commit.

## License

GPL v2 or later, the same license as WordPress.
