#!/usr/bin/env bash
# tests/Fixtures/gateway-contract/v1 must be byte-for-byte the gateway's contracts/v1 (schemas,
# examples) at the commit pinned in CONTRACT_REF. Needs GH_TOKEN with read access to that repo.
set -e
dir=tests/Fixtures/gateway-contract
repo=$(sed -n 1p $dir/CONTRACT_REF); ref=$(sed -n 2p $dir/CONTRACT_REF)
remote=$(gh api "repos/$repo/git/trees/$ref?recursive=1" --jq '.tree[] | select(.type=="blob") | .path | select(test("^contracts/v1/(schemas|examples)/"))' | sed 's#^contracts/##' | sort)
local=$(cd $dir && find v1 -type f | sort)
if [ "$remote" != "$local" ]; then echo "::error::file list differs from $repo@$ref"; diff <(echo "$remote") <(echo "$local") || true; exit 1; fi
tmp=$(mktemp)
for f in $local; do
  gh api -H "Accept: application/vnd.github.raw" "repos/$repo/contents/contracts/$f?ref=$ref" > "$tmp"
  cmp -s "$tmp" "$dir/$f" || { echo "::error file=$dir/$f::differs from $repo@$ref"; exit 1; }
done
echo "copy matches $repo@$ref ($(echo "$local" | wc -l) files)"
