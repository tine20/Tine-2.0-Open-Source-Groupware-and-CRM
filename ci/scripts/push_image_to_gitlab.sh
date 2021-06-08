#!/bin/sh  
set -e
NAME=$1

docker login -u "${CI_REGISTRY_USER}" -p "${CI_REGISTRY_PASSWORD}" "${CI_REGISTRY}"

FROM_IMAGE="${REGISTRY}/${NAME}-commit:${CI_PIPELINE_ID}-${PHP_VERSION}"
DESTINATION_IMAGE="${CI_REGISTRY}/tine20/tine20/${NAME}:$(echo $CI_COMMIT_REF_NAME | sed sI/I-Ig)-${PHP_VERSION}"

docker pull "${FROM_IMAGE}"
docker tag "${FROM_IMAGE}" "${DESTINATION_IMAGE}"
docker push "${DESTINATION_IMAGE}"
