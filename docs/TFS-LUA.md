# TFS Webhook Integration Examples

> **Note:** These snippets are examples only. They should be reviewed and adapted before use on your TFS server. Do **not** execute them on the web application server.

## Webhook sender stub (`data/scripts/webhook.lua`)
```lua
local http = require("socket.http")
local ltn12 = require("ltn12")
local cjson = require("cjson")

local secret = "{{WEBHOOK_SECRET}}"
local url = "{{SITE_URL}}/api/webhooks/tfs.php"

local function hmac_sha256(key, data)
    -- Replace this with a real HMAC-SHA256 implementation, such as one provided
    -- by luasocket's crypto module or a custom binding.
    return some_hmac(key, data)
end

local function send(evt, data)
    local body = cjson.encode({ event = evt, data = data, ts = os.time() })
    local sig = hmac_sha256(secret, body)
    local response = {}

    http.request({
        url = url,
        method = "POST",
        headers = {
            ["Content-Type"] = "application/json",
            ["X-TFS-Signature"] = sig,
            ["Content-Length"] = tostring(#body),
        },
        source = ltn12.source.string(body),
        sink = ltn12.sink.table(response),
    })
end

function onLogin(player)
    send("player_login", { name = player:getName(), level = player:getLevel() })
end

function onLogout(player)
    send("player_logout", { name = player:getName() })
end

-- Example custom event trigger:
-- send("raid_start", { raid = "Orshabaal", position = { x = 1000, y = 1000, z = 7 } })
```

## Status snapshot globalevent
```lua
local function report_status()
    send("status_snapshot", {
        online = getPlayersOnline(),
        uptime = os.clock(),
        tps = getServerTPS(),
    })
end

-- Register this function with a globalevent that fires every N seconds.
```
