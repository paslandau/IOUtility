## 0.1.1

 - updated repositories to local satis installation
 - added tests for CSV writing/reading. Caution: [https://bugs.php.net/bug.php?id=43225](https://bugs.php.net/bug.php?id=43225) is not solved (writing/reading fails on \" values)
 - added tests for CsvRows class
 - removed hasHeadline() from CsvRows since it didn't really make sense. A headline should be added in any case - even for numerical columns

## 0.1.0

 - changed package name from IOUtility to io-utility

###0.0.2

- fixed getAbsolutePath to take trailing slashes into account
- added tests for combinePaths

###0.0.1

- Initial commit