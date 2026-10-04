#!/usr/bin/env bash
# One cron for every core website on a Plesk server.
#
# Every minute it finds the websites running this core under /var/www/vhosts (an `artisan` next to
# app/Support/CoreManifest.php) and runs `php artisan schedule:run` in each — as the user that owns
# the website, never as root, so storage/ and bootstrap/cache stay writable for the website. A new
# website is picked up within 10 minutes (the list is rescanned that often); websites that are not
# this core are never touched.
#
#   sudo bash plesk-cron.sh --install     copy to /usr/local/bin, add /etc/cron.d/matbaows-core
#   sudo matbaows-cron --list             the websites it found, with owner, php and core version
#   sudo matbaows-cron                    one run (what cron does every minute)
#   sudo matbaows-cron --uninstall
#
# Settings (environment or /etc/matbaows-cron.conf):
#   VHOSTS=/var/www/vhosts   where the websites live
#   PHP=                     php binary to use; empty = the newest /opt/plesk/php/8.x (8.2+)
#   JOBS=8                   websites run in parallel
#   LOG=/var/log/matbaows-cron.log   failures only
set -u

[ -r /etc/matbaows-cron.conf ] && . /etc/matbaows-cron.conf
VHOSTS="${VHOSTS:-/var/www/vhosts}"
PHP="${PHP:-}"
JOBS="${JOBS:-8}"
LOG="${LOG:-/var/log/matbaows-cron.log}"
LOCK_DIR="${LOCK_DIR:-/run/matbaows-cron}"
SELF_PATH=/usr/local/bin/matbaows-cron
CRON_FILE=/etc/cron.d/matbaows-core

pick_php() {
    if [ -n "$PHP" ]; then echo "$PHP"; return; fi
    local candidate best=""
    for candidate in $(ls -d /opt/plesk/php/8.*/bin/php 2>/dev/null | sort -V) /usr/bin/php; do
        [ -x "$candidate" ] || continue
        if "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then best="$candidate"; fi
    done
    echo "$best"
}

# Website roots: directories holding artisan and the core manifest. Plesk keeps a domain in
# <vhost>/httpdocs and subdomains or extra sites in other folders of the same <vhost>.
find_sites() {
    find "$VHOSTS" -mindepth 2 -maxdepth 4 -name artisan -type f \
        -not -path "$VHOSTS/system/*" -not -path "$VHOSTS/chroot/*" -not -path '*/.skel/*' \
        -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null |
    while read -r artisan; do
        root="${artisan%/artisan}"
        [ -f "$root/app/Support/CoreManifest.php" ] && echo "$root"
    done | sort -u
}

# The scan walks every vhost: do it every 10 minutes, not every minute.
cached_sites() {
    local cache="$LOCK_DIR/sites"
    if [ ! -s "$cache" ] || [ -n "$(find "$cache" -mmin +10 2>/dev/null)" ]; then
        find_sites > "$cache.tmp" && mv "$cache.tmp" "$cache"
    fi
    cat "$cache"
}

owner_of() { stat -c %U "$1/artisan" 2>/dev/null; }

run_site() {
    local root="$1" php="$2" owner lock started output code
    owner="$(owner_of "$root")"
    if [ -z "$owner" ] || [ "$owner" = root ]; then
        echo "$(date '+%F %T') $root: bỏ qua (chủ sở hữu '$owner'; không chạy website bằng root)" >> "$LOG"
        return
    fi
    lock="$LOCK_DIR/$(echo "$root" | md5sum | cut -c1-16).lock"
    # The previous run still going (an update takes minutes): skip this minute, no pile-up.
    exec 9>"$lock"
    flock -n 9 || return
    output="$(cd "$root" && HOME="$(dirname "$root")" timeout 1800 runuser -u "$owner" -- "$php" artisan schedule:run 2>&1)"
    code=$?
    if [ $code -ne 0 ]; then
        printf '%s %s (user %s): exit %s\n%s\n' "$(date '+%F %T')" "$root" "$owner" "$code" "$(echo "$output" | tail -20)" >> "$LOG"
    fi
}

list_sites() {
    local php root
    php="$(pick_php)"
    echo "PHP: ${php:-KHÔNG TÌM THẤY php 8.2+}"
    printf '%-60s %-22s %s\n' WEBSITE OWNER CORE
    find_sites | while read -r root; do
        printf '%-60s %-22s %s\n' "$root" "$(owner_of "$root")" "$(cat "$root/VERSION" 2>/dev/null || echo '?')"
    done
}

install_self() {
    [ "$(id -u)" -eq 0 ] || { echo "Chạy bằng root: sudo bash $0 --install"; exit 1; }
    command -v flock >/dev/null && command -v runuser >/dev/null || { echo "Thiếu flock/runuser (gói util-linux)."; exit 1; }
    install -m 0755 "$0" "$SELF_PATH"
    cat > "$CRON_FILE" <<EOF
# Laravel scheduler for every MatBao core website on this server (infra/plesk-cron.sh).
* * * * * root $SELF_PATH >/dev/null 2>&1
EOF
    chmod 0644 "$CRON_FILE"
    cat > /etc/logrotate.d/matbaows-cron <<EOF
$LOG {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
EOF
    echo "Đã cài: $SELF_PATH và $CRON_FILE (chạy mỗi phút)."
    echo
    list_sites
    echo
    echo "Lỗi (nếu có) ghi vào $LOG."
}

uninstall_self() {
    rm -f "$CRON_FILE" "$SELF_PATH" /etc/logrotate.d/matbaows-cron
    echo "Đã gỡ. (Các website quay về không có cron.)"
}

main() {
    case "${1:-}" in
        --install) install_self; return ;;
        --uninstall) uninstall_self; return ;;
        --list) list_sites; return ;;
        '') ;;
        *) sed -n '2,20p' "$0"; return ;;
    esac

    [ "$(id -u)" -eq 0 ] || { echo "Chạy bằng root (cron của root)."; exit 1; }
    local php
    php="$(pick_php)"
    if [ -z "$php" ]; then
        echo "$(date '+%F %T') không tìm thấy php 8.2+ (đặt PHP= trong /etc/matbaows-cron.conf)" >> "$LOG"
        exit 1
    fi
    mkdir -p "$LOCK_DIR"
    export -f run_site owner_of
    export LOG LOCK_DIR
    cached_sites | xargs -r -P "$JOBS" -I{} bash -c 'run_site "$1" "$2"' _ {} "$php"
}

main "$@"
