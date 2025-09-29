# PHP Html2Text

[![CI](https://github.com/ineersa/php-html2text/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/ineersa/php-html2text/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/ineersa/php-html2text/branch/main/graph/badge.svg)](https://codecov.io/gh/ineersa/php-html2text)


`html2text` converts a page of HTML into clean, easy-to-read plain ASCII text. Better yet, that ASCII also happens to be valid Markdown (a text-to-HTML format).

It is a PHP port of [Alir3z4/html2text](https://github.com/Alir3z4/html2text)


```BASH
 php -d xdebug.mode=debug -d xdebug.client_host=127.0.0.1 -d xdebug.client_port=9003 -d xdebug.start_with_request=yes ./bin/html-to-markdown.php ./tests/files/bodywidth_newline.html
```



























## License

This project is licensed under the [GNU General Public License v3.0 or later](LICENSE).

It is a PHP port of [Alir3z4/html2text](https://github.com/Alir3z4/html2text),  
which is licensed under the GPL as well.  
All credit goes to the original authors for their work.
