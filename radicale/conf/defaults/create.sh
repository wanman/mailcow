#!/bin/bash
mkdir /var/lib/radicale/collections/$1/
cp /var/lib/radicale/*ics* /var/lib/radicale/collections/$1/
cp /var/lib/radicale/*vcf* /var/lib/radicale/collections/$1/
chmod 640 /var/lib/radicale/collections/$1/*
chmod 750 /var/lib/radicale/collections/$1
