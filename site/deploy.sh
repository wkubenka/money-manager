#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env"

if [[ -f "$ENV_FILE" ]]; then
    set -a
    source "$ENV_FILE"
    set +a
fi

S3_BUCKET="${S3_BUCKET:?Set S3_BUCKET in .env or as env var}"
CF_DISTRIBUTION_ID="${CF_DISTRIBUTION_ID:?Set CF_DISTRIBUTION_ID in .env or as env var}"

echo "=== Deploying static site ==="
aws s3 sync site/ "s3://${S3_BUCKET}/" \
    --delete \
    --cache-control "max-age=3600" \
    --profile personal

echo "=== Invalidating CloudFront cache ==="
aws cloudfront create-invalidation \
    --distribution-id "${CF_DISTRIBUTION_ID}" \
    --paths "/*" \
    --profile personal

echo "=== Site deploy complete ==="
