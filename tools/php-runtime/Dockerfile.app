ARG APP_IMAGE
ARG RUNTIME_IMAGE
FROM ${APP_IMAGE} AS application
FROM ${RUNTIME_IMAGE}
COPY --from=application /app/ /app/
COPY --from=application /demo/ /demo/
COPY --from=application /opt/manual-demo-marker /opt/manual-demo-marker
COPY checks/ /opt/runtime-security/
WORKDIR /app
ENTRYPOINT ["sh", "/demo/entrypoint.sh"]
