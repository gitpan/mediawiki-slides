#!/usr/bin/perl -w

# some basic tests

use Test::More;
use strict;
use File::Spec;

BEGIN
  {
  plan tests => 2;
  chdir 't' if -d 't';
  }

my $updir = File::Spec->updir();

my $dir = File::Spec->catdir($updir, 'extensions');
is (-f File::Spec->catfile( $dir, 'Slides.php'), 1, 'Slides.php exists');

is (-f File::Spec->catfile( $updir, 'README'), 1, 'README exists');

