A pinned copy of the MatBao gateway's `contracts/v1` (`schemas/`, `examples/`), the contract this
website's client code is tested against (`tests/Feature/GatewayContractTest.php`). The gateway
owns it; see `contracts/README.md` in M-t-Bao-WS/MatBaoAI.

- `CONTRACT_REF`: the repository, then the full commit SHA the copy comes from.
- `SHA256SUMS`: checksums of the copy. `GatewayContractTest` fails if a file was edited by hand.
  CI (`tests/Support/check-gateway-contract.sh`, secret `GATEWAY_CONTRACT_READ_TOKEN`) fails if
  the copy differs from the gateway at that commit.

To move to a newer contract, in a PR of its own: put the new SHA in `CONTRACT_REF`, copy
`contracts/v1/schemas` and `contracts/v1/examples` from that commit, then regenerate
`SHA256SUMS` (`find v1 -type f | sort | xargs sha256sum > SHA256SUMS`, run in this folder).
Never edit the copied files.
