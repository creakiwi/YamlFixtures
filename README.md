# YamlFixtures

## Overview

A simple abstraction class to use yaml fixtures, instead of PHP classes, on top of DoctrineFixturesBundle.
It also allows the use of particular keywords such as %self% (the reference key of the yaml definition, in case of key => mixed value declaration) or %field% (where "field" refers to a previously defined field).
Finally, this bundle handle One-to-One, Many-to-One and Many-to-Many relations, with the @ prefix.

## Warning

The bundle isn't currently unit tested, use it at your own risks! You might need it to bootstrap your database, but never use it once you are in production mode (this rules is also true for DoctrineFixturesBundle in my opinion).

## Examples

I will post some examples in the near future