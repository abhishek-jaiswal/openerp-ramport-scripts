#!/usr/bin/env python

import re

shakes = open("log.txt", "r")

for line in shakes:
    if re.match("(.*)(I|i)nsert(.*)", line):
        #x[:-2]
        print line[:-7]