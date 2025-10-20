#!/bin/bash
# create_instance_oci.sh
# Usage: set the required environment variables below or export them before running.
# Requires: OCI CLI installed and configured (oci setup config) and jq installed.
# This script launches an Oracle Cloud compute instance and passes the local cloud-init file as user-data.

set -euo pipefail

: ${COMPARTMENT_ID:=""}
: ${AVAILABILITY_DOMAIN:=""}
: ${IMAGE_ID:=""}
: ${SUBNET_ID:=""}
: ${SSH_PUB_KEY_FILE:="~/.ssh/id_rsa.pub"}
: ${DISPLAY_NAME:="mycash-instance"}
: ${SHAPE:=VM.Standard.E2.1.Micro}

if [ -z "$COMPARTMENT_ID" ] || [ -z "$AVAILABILITY_DOMAIN" ] || [ -z "$IMAGE_ID" ] || [ -z "$SUBNET_ID" ]; then
  echo "ERROR: Please set COMPARTMENT_ID, AVAILABILITY_DOMAIN, IMAGE_ID and SUBNET_ID environment variables." >&2
  echo "Example: export COMPARTMENT_ID=ocid1.compartment.oc1..aaaaaaaaxxx" >&2
  exit 2
fi

if [ ! -f "$SSH_PUB_KEY_FILE" ]; then
  echo "ERROR: SSH public key file not found: $SSH_PUB_KEY_FILE" >&2
  exit 2
fi

SSH_PUB_KEY=$(cat "$SSH_PUB_KEY_FILE" | tr -d '\n')

# user-data file path
USER_DATA_FILE="$(pwd)/scripts/cloud-init-oracle.yml"
if [ ! -f "$USER_DATA_FILE" ]; then
  echo "ERROR: cloud-init file not found at $USER_DATA_FILE" >&2
  exit 2
fi

echo "Launching instance in compartment $COMPARTMENT_ID (AD: $AVAILABILITY_DOMAIN) with image $IMAGE_ID and subnet $SUBNET_ID"

oci compute instance launch \
  --availability-domain "$AVAILABILITY_DOMAIN" \
  --compartment-id "$COMPARTMENT_ID" \
  --shape "$SHAPE" \
  --display-name "$DISPLAY_NAME" \
  --image-id "$IMAGE_ID" \
  --subnet-id "$SUBNET_ID" \
  --assign-public-ip true \
  --user-data-file "$USER_DATA_FILE" \
  --metadata "{\"ssh_authorized_keys\": \"$SSH_PUB_KEY\"}" \
  --wait-for-state RUNNING

echo "Instance launched (RUNNING). Use 'oci compute instance list --compartment-id <compartment>' to view." 

# Optionally print the public IP
INST_ID=$(oci compute instance list --compartment-id "$COMPARTMENT_ID" --display-name "$DISPLAY_NAME" --query "data[0].id" --raw-output)
if [ -n "$INST_ID" ]; then
  PUBLIC_IP=$(oci compute instance list-vnics --instance-id "$INST_ID" --compartment-id "$COMPARTMENT_ID" --query "data[0]." --raw-output 2>/dev/null || true)
  echo "Instance OCID: $INST_ID"
fi

echo "Done. SSH with: ssh ubuntu@<public-ip> (or check OCI console for assigned IP)"
