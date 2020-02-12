# Pimcore Document Copier
Pimcore bundle for copying documents between environments

**Table of Contents**
- [Pimcore Document Copier](#pimcore-document-copier)
	- [Compatibility](#compatibility)
	- [Installing/Getting started](#installinggetting-started)
	- [Usage](#usage)
	- [Testing](#testing)
	- [Contributing](#contributing)
	- [Licence](#licence)
	- [Standards & Code Quality](#standards--code-quality)
	- [About Authors](#about-authors)

## Compatibility
This module was tested on Pimcore 6.1.2 @ PHP 7.3.

## Installing/Getting started

Add repository to your project's composer.json:
```json
{
    "repositories": [
        {
          "type": "vcs",
          "url": "ssh://git@gitlab.divante.pl:60022/pdunaj/pimcore-document-copier.git"
        }
      ]
}
```

Install latest version from repo:
```bash
composer require divante/pimcore-document-copier:dev-master
```

Enable the bundle:
```bash
bin/console pimcore:bundle:enable DocumentCopierBundle
```

Import example documents:
```bash
bin/console document-copier:import --path=/codecept-document-copier/foo/bar --root=vendor/divante/pimcore-document-copier/app/Resources/test_root --recursiveDepth=2
```

View imported documents in Pimcore admin panel


## Usage

## Testing

Run tests locally:
```bash
vendor/bin/codecept run -c tests/codeception.dist.yml
```

Or with gitlab-runner:
```bash
gitlab-runner exec docker codeception
```

## Contributing
If you'd like to contribute, please fork the repository and use a feature branch. Pull requests are warmly welcome.

## Licence 
Pimcore Document Copier source code is completely free and released under the 
[GNU General Public License v3.0]({repository_url}/blob/master/LICENSE).

## Standards & Code Quality
This module respects all Pimcore 6 code quality rules and our own PHPCS and PHPMD rulesets.

## About Authors
![Divante-logo](http://divante.co/logo-HG.png "Divante")

We are a Software House from Europe, existing from 2008 and employing about 150 people. Our core competencies are built 
around Magento, Pimcore and bespoke software projects (we love Symfony3, Node.js, Angular, React, Vue.js). 
We specialize in sophisticated integration projects trying to connect hardcore IT with good product design and UX.

We work for Clients like INTERSPORT, ING, Odlo, Onderdelenwinkel and CDP, the company that produced The Witcher game. 
We develop two projects: [Open Loyalty](http://www.openloyalty.io/ "Open Loyalty") - an open source loyalty program 
and [Vue.js Storefront](https://github.com/DivanteLtd/vue-storefront "Vue.js Storefront").

We are part of the OEX Group which is listed on the Warsaw Stock Exchange. Our annual revenue has been growing at a 
minimum of about 30% year on year.

Visit our website [Divante.co](https://divante.co/ "Divante.co") for more information.
