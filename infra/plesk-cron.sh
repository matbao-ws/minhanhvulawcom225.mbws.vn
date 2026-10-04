#!/usr/bin/env bash
# One cron for the core websites on a Plesk server.
#
# Every minute it runs `php artisan schedule:run` in each website that the core update system
# manages — as the user that owns the website, never as root — and in nothing else.
#
# A website is included only when ALL of these hold:
#   - <root>/artisan and <root>/app/Support/CoreManifest.php exist (this core);
#   - <root>/storage/app/core/installed.json exists: the update system manages it (written by
#     deploy.py and by every core update). Older projects never have it, so they are never touched;
#   - the website's owner has no cron of its own running schedule:run there (no double runs);
#   - <root>/storage/app/core/no-shared-cron does not exist (opt out);
#   - the owner is not root.
#
# Load: at most JOBS websites at a time, at low CPU/IO priority; a run still going when the next
# minute starts makes that minute skip (no pile-up); a busy server (load above LOAD_MAX) skips the
# minute; the website list is rescanned every 10 minutes, not every minute.
#
#   sudo bash plesk-cron.sh --install     copy to /usr/local/bin, add /etc/cron.d/matbaows-core
#   sudo matbaows-cron --list             every core website found, and whether it is included
#   sudo matbaows-cron                    one run (what cron does every minute)
#   sudo matbaows-cron --uninstall
#
# Settings (environment or /etc/matbaows-cron.conf):
#   VHOSTS=/var/www/vhosts   where the websites live
#   PHP=                     php binary; empty = the newest /opt/plesk/php/8.x that is 8.2+
#   JOBS=4                   websites run in parallel
#   LOAD_MAX=                skip the minute above this 1-minute load average; empty = CPU count x 2
#   LOG=/var/log/matbaows-cron.log   failures and skipped minutes only
set -u

[ -r /etc/matbaows-cron.conf ] && . /etc/matbaows-cron.conf
VHOSTS="${VHOSTS:-/var/www/vhosts}"
PHP="${PHP:-}"
JOBS="${JOBS:-4}"
LOAD_MAX="${LOAD_MAX:-$(( $(nproc 2>/dev/null || echo 2) * 2 ))}"
LOG="${LOG:-/var/log/matbaows-cron.log}"
LOCK_DIR="${LOCK_DIR:-/run/matbaows-cron}"
SELF_PATH=/usr/local/bin/matbaows-cron
CRON_FILE=/etc/cron.d/matbaows-core

log() { echo "$(date '+%F %T') $*" >> "$LOG"; }

pick_php() {
    if [ -n "$PHP" ]; then echo "$PHP"; return; fi
    local candidate best=""
    for candidate in $(ls -d /opt/plesk/php/8.*/bin/php 2>/dev/null | sort -V) /usr/bin/php; do
        [ -x "$candidate" ] || continue
        if "$candidate" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>/dev/null; then best="$candidate"; fi
    done
    echo "$best"
}

# Directories holding artisan and the core manifest. Plesk keeps a domain in <vhost>/httpdocs and
# subdomains or extra sites in other folders of the same <vhost>.
find_core_roots() {
    find "$VHOSTS" -mindepth 2 -maxdepth 4 -name artisan -type f \
        -not -path "$VHOSTS/system/*" -not -path "$VHOSTS/chroot/*" -not -path '*/.skel/*' \
        -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null |
    while read -r artisan; do
        root="${artisan%/artisan}"
        [ -f "$root/app/Support/CoreManifest.php" ] && echo "$root"
    done | sort -u
}

owner_of() { stat -c %U "$1/artisan" 2>/dev/null; }

# Empty when the website is included, otherwise why not.
exclusion() {
    local root="$1" owner
    owner="$(owner_of "$root")"
    [ -f "$root/storage/app/core/installed.json" ] || { echo 'chưa có mốc core (installed.json): dự án cũ hoặc chưa deploy bằng deploy.py mới'; return; }
    [ -f "$root/storage/app/core/no-shared-cron" ] && { echo 'có file no-shared-cron'; return; }
    [ -n "$owner" ] && [ "$owner" != root ] || { echo "chủ sở hữu '$owner'"; return; }
    if crontab -l -u "$owner" 2>/dev/null | grep -v '^\s*#' | grep -F "$root" | grep -q 'schedule:run'; then
        echo "đã có cron riêng của $owner"
        return
    fi
}

# The scan walks every vhost: do it every 10 minutes, not every minute.
included_sites() {
    local cache="$LOCK_DIR/sites" root
    if [ ! -s "$cache" ] || [ -n "$(find "$cache" -mmin +10 2>/dev/null)" ]; then
        find_core_roots | while read -r root; do
            [ -z "$(exclusion "$root")" ] && echo "$root"
        done > "$cache.tmp"
        mv "$cache.tmp" "$cache"
    fi
    cat "$cache"
}

run_site() {
    local root="$1" php="$2" owner output code
    owner="$(owner_of "$root")"
    [ -n "$owner" ] && [ "$owner" != root ] || return
    # This website's previous run still going (an update takes minutes): skip it this minute.
    exec 9>"$LOCK_DIR/$(echo "$root" | md5sum | cut -c1-16).lock"
    flock -n 9 || return
    output="$(cd "$root" && HOME="$(dirname "$root")" nice -n 10 ionice -c2 -n7 \
        timeout 1800 runuser -u "$owner" -- "$php" artisan schedule:run 2>&1)"
    code=$?
    if [ $code -ne 0 ]; then
        log "$root (user $owner): exit $code"
        echo "$output" | tail -20 >> "$LOG"
    fi
}

list_sites() {
    local php root why
    php="$(pick_php)"
    echo "PHP: ${php:-KHÔNG TÌM THẤY php 8.2+}    JOBS=$JOBS    LOAD_MAX=$LOAD_MAX"
    printf '%-55s %-18s %-8s %s\n' WEBSITE OWNER CORE 'CHẠY?'
    find_core_roots | while read -r root; do
        why="$(exclusion "$root")"
        printf '%-55s %-18s %-8s %s\n' "$root" "$(owner_of "$root")" "$(cat "$root/VERSION" 2>/dev/null || echo '?')" \
            "$([ -z "$why" ] && echo 'CÓ' || echo "không: $why")"
    done
}

install_self() {
    [ "$(id -u)" -eq 0 ] || { echo "Chạy bằng root: sudo bash $0 --install"; exit 1; }
    for tool in flock runuser ionice timeout crontab; do
        command -v "$tool" >/dev/null || { echo "Thiếu lệnh $tool."; exit 1; }
    done
    install -m 0755 "$0" "$SELF_PATH"
    cat > "$CRON_FILE" <<EOF
# Laravel scheduler for the MatBao core websites on this server (infra/plesk-cron.sh).
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
    rm -f "$LOCK_DIR/sites"
    echo "Đã cài: $SELF_PATH và $CRON_FILE (chạy mỗi phút)."
    echo
    list_sites
    echo
    echo "Chỉ các dòng CHẠY? = CÓ được cron này chạy. Lỗi (nếu có) ghi vào $LOG."
}

uninstall_self() {
    rm -f "$CRON_FILE" "$SELF_PATH" /etc/logrotate.d/matbaows-cron
    rm -rf "$LOCK_DIR"
    echo "Đã gỡ. Các website quay về không có cron chung."
}

main() {
    case "${1:-}" in
        --install) install_self; return ;;
        --uninstall) uninstall_self; return ;;
        --list) list_sites; return ;;
        '') ;;
        *) sed -n '2,30p' "$0"; return ;;
    esac

    [ "$(id -u)" -eq 0 ] || { echo "Chạy bằng root (cron của root)."; exit 1; }
    mkdir -p "$LOCK_DIR"

    # The previous minute's run still going: skip this one instead of stacking runs.
    exec 8>"$LOCK_DIR/run.lock"
    flock -n 8 || { log 'lượt trước chưa xong: bỏ qua phút này'; exit 0; }

    local load php
    load="$(cut -d' ' -f1 /proc/loadavg 2>/dev/null || echo 0)"
    if awk -v l="$load" -v m="$LOAD_MAX" 'BEGIN { exit !(l > m) }'; then
        log "máy chủ đang tải cao (load $load > $LOAD_MAX): bỏ qua phút này"
        exit 0
    fi

    php="$(pick_php)"
    if [ -z "$php" ]; then
        log 'không tìm thấy php 8.2+ (đặt PHP= trong /etc/matbaows-cron.conf)'
        exit 1
    fi

    export -f run_site owner_of log
    export LOG LOCK_DIR
    included_sites | xargs -r -P "$JOBS" -I{} bash -c 'run_site "$1" "$2"' _ {} "$php"
}

main "$@"
