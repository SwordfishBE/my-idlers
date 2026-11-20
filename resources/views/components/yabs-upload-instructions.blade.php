@props(['serverId'])
@if(config('yabs.allow_legacy_url_tokens', true) && request()->isSecure())
<p>For a one-command upload, replace <code>YOUR_API_TOKEN</code> with your API token:</p>
<pre class="yabs-command"><code>bash yabs.sh -s '{{ route('api.store-yabs-legacy', ['server' => $serverId, 'key' => 'YOUR_API_TOKEN']) }}'</code></pre>
<p class="text-muted small">The upload URL contains your token and may appear in shell history and access logs. Use the Bearer option below to keep the token out of the URL.</p>
<details>
<summary>Upload using a Bearer token</summary>
@endif
<p class="text-muted">Download and inspect the official YABS script as <code>yabs.sh</code>, then run:</p>
<pre class="yabs-command"><code>(
  set +x
  umask 077
  result=$(mktemp "${TMPDIR:-/tmp}/myidlers-yabs.XXXXXXXX") || exit 1
  trap 'rm -f -- "$result"' EXIT
  bash yabs.sh -w "$result" || exit 1
  read -rs -p 'My Idlers API token: ' MYIDLERS_TOKEN; printf '\n'
  case "$MYIDLERS_TOKEN" in ''|*[!a-zA-Z0-9]*) echo 'Invalid token format' &gt;&amp;2; exit 1;; esac
  printf 'header = "Authorization: Bearer %s"\n' "$MYIDLERS_TOKEN" | \
    curl --config - --proto '=https' --fail --silent --show-error \
      -H 'Content-Type: application/json' --data-binary "@$result" \
      '{{ route('api.store-yabs', ['server' => $serverId]) }}'
  unset MYIDLERS_TOKEN
)</code></pre>
<p class="text-muted small">The token is read privately and is not placed in the URL or shell history. Existing URL-token scripts remain supported during migration, but can expose their token in access logs.</p>
@if(config('yabs.allow_legacy_url_tokens', true) && request()->isSecure())
</details>
@endif
