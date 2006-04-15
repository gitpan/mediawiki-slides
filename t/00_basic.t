#!/usr/bin/perl -w

# some basic tests

use Test::More;

BEGIN
  {
  plan tests => 1;
  chdir 't' if -d 't';
  }

is (-f '../extensions/Slides.php', 1, 'Slides.php exists');

