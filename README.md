ODG-Serialiser
==============

A PHP script to transform a single page ODG (OpenDocument Graphics) into a multi-page file while replacing strings and generating page based number sequences.

I wrote this to be able to create a Digital Betamax tape cover in OpenOffice with placeholders for the series name and episode and then automatically generate all the pages I needed with incremental episode numbers.

You can set up one incremental string replacement specifying the string to replace, the starting number and the ending one. You can also specify limitless further string substitutions, useful to generate multiple documents from a template document.

==============
