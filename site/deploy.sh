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

echo "=== Building Tailwind CSS ==="
npx @tailwindcss/cli -i site/src/input.css -o site/app.css --minify

HASH=$(shasum -a 256 site/app.css | cut -c1-10)
HASHED_CSS="app.${HASH}.css"

echo "=== Staging deploy (hash: ${HASH}) ==="
DEPLOY_DIR=$(mktemp -d)
trap 'rm -rf "$DEPLOY_DIR"' EXIT

rsync -a \
    --include '*.html' \
    --include '*.svg' \
    --include '*.png' \
    --include '*.ico' \
    --include 'fonts/' \
    --include 'fonts/**' \
    --exclude '*' \
    site/ "$DEPLOY_DIR/"

cp site/app.css "$DEPLOY_DIR/${HASHED_CSS}"

for f in "$DEPLOY_DIR"/*.html; do
    sed -i.bak "s|/app\.css|/${HASHED_CSS}|g" "$f"
    rm "$f.bak"
done

echo "=== Uploading immutable assets (1y cache) ==="
aws s3 cp "$DEPLOY_DIR/${HASHED_CSS}" "s3://${S3_BUCKET}/${HASHED_CSS}" \
    --cache-control "public, max-age=31536000, immutable" \
    --profile personal

aws s3 sync "$DEPLOY_DIR/fonts/" "s3://${S3_BUCKET}/fonts/" \
    --cache-control "public, max-age=31536000, immutable" \
    --profile personal

echo "=== Syncing HTML and other files (5m cache) ==="
aws s3 sync "$DEPLOY_DIR/" "s3://${S3_BUCKET}/" \
    --delete \
    --exclude "fonts/*" \
    --exclude "app.*.css" \
    --cache-control "public, max-age=300" \
    --profile personal

echo "=== Invalidating CloudFront cache ==="
aws cloudfront create-invalidation \
    --distribution-id "${CF_DISTRIBUTION_ID}" \
    --paths "/*" \
    --profile personal

echo "=== Site deploy complete ==="
