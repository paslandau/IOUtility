#todo

 - there seems to be some sort of bug that causes a CSV file to be read twice when using the `checked` reading, wasn't able to figure out why yet.
 - keep an eye on bug [https://bugs.php.net/bug.php?id=43225](https://bugs.php.net/bug.php?id=43225) (writing/reading fails on \" values)
 - docu
 
#dev-master

##0.8

 - added IOUtil::getPathToTempFile() *not tested yet*

###0.1.2
 
 - updated to newest version of [League\csv](https://github.com/thephpleague/csv) once dev-master got tagged due to [issue #99: SplFileObject Flags have no effect / empty lines cannot be ignored](https://github.com/thephpleague/csv/issues/99)
 - switched CSV library from [Goodby\csv](https://github.com/goodby/csv) to [League\csv](https://github.com/thephpleague/csv) after encountering performance issues with Goodby
 - removed CsvRows class since it wasn't useful in practice
 - added more comprehensive tests for csv files
 - introduced EncodingStreamSettings as a more comprehensive means of defining an encoding. Background: A bug that appeared upon reading utf-8 encoded files in that a character was 2 bytes long and was torn apart during the chunked reading of the `EncodingStreamFilter`

###0.1.1

 - updated repositories to local satis installation
 - added tests for CSV writing/reading. Caution: [https://bugs.php.net/bug.php?id=43225](https://bugs.php.net/bug.php?id=43225) is not solved (writing/reading fails on \" values)
 - added tests for CsvRows class
 - removed hasHeadline() from CsvRows since it didn't really make sense. A headline should be added in any case - even for numerical columns

##0.1.0

 - changed package name from IOUtility to io-utility

###0.0.2

- fixed getAbsolutePath to take trailing slashes into account
- added tests for combinePaths

###0.0.1

- Initial commit