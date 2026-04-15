FROM alpine:3.19
ARG TARGETARCH
COPY seeds/seeder-${TARGETARCH} /usr/local/bin/seeder
ENTRYPOINT ["/usr/local/bin/seeder"]
