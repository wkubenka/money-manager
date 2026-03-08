#!/usr/bin/env bash
set -euo pipefail

S3_BUCKET="${S3_BUCKET:?Set S3_BUCKET env var}"
CF_DISTRIBUTION_ID="${CF_DISTRIBUTION_ID:?Set CF_DISTRIBUTION_ID env var}"

echo "=== Deploying static site ==="
aws s3 sync site/ "s3://${S3_BUCKET}/" \
    --delete \
    --cache-control "max-age=3600"

echo "=== Invalidating CloudFront cache ==="
aws cloudfront create-invalidation \
    --distribution-id "${CF_DISTRIBUTION_ID}" \
    --paths "/*"

echo "=== Site deploy complete ==="
