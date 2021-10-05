#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."

newVersion=$1

fileName="jira-search.alfred3workflow"

echo "cleanup"
rm -Rf $fileName

echo "replacing version"
sed "s/{VERSION}/$newVersion/g" src/info.plist.example > src/info.plist

echo "create zip"
cd src && zip -r "../$fileName" . -x "*.DS_Store" -x "*/\.DS_Store" -x "info.plist.example" && cd ..

open $fileName
