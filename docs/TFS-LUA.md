# TFS Bridge Lua Examples

The snippets below illustrate how to publish webhook events and process bridge
jobs from an OTServ/TFS Lua environment. Adapt the HTTP helper functions to the
facilities that your server distribution provides.

Set `WEBHOOK_SECRET` and `BRIDGE_SECRET` inside `Site/config.php` on the website
before using these examples. Both values must match the secrets that your Lua
scripts rely on when signing requests or authenticating with the bridge.

## Sending Webhook Events

```lua
local json = require('json') -- Replace with your JSON encoder
local http = require('http') -- Replace with your HTTP client

local WEBHOOK_SECRET = 'replace-with-webhook-secret'

local function hmac_sha256(key, message)
    -- Use your Lua distribution's crypto bindings
    return crypto.hmac.sha256(key, message)
end

local function send_webhook(event_name, event_data)
    local payload = {
        event = event_name,
        data = event_data,
        ts = os.time(),
    }

    local body = json.encode(payload)
    local signature = hmac_sha256(WEBHOOK_SECRET, body)

    local response = http.post('https://example.com/api/webhook.php', body, {
        ['Content-Type'] = 'application/json',
        ['X-Signature'] = signature,
    })

    if response.status ~= 200 then
        print('Webhook failed: ' .. response.status .. ' ' .. response.body)
    end
end

-- Example usage:
function onLogin(player)
    send_webhook('player_login', {
        guid = player:getGuid(),
        name = player:getName(),
    })
end

function onLogout(player)
    send_webhook('player_logout', {
        guid = player:getGuid(),
        name = player:getName(),
    })
end
```

## Polling and Completing Jobs

```lua
local json = require('json')
local http = require('http')

local BRIDGE_SECRET = 'replace-with-bridge-secret'

local BASE_URL = 'https://example.com/api/jobs_pull.php'

local function poll_jobs()
    local response = http.get(BASE_URL .. '?limit=5', {
        ['Authorization'] = 'Bearer ' .. BRIDGE_SECRET,
    })

    if response.status ~= 200 then
        print('Job poll failed: ' .. response.status .. ' ' .. response.body)
        return {}
    end

    local decoded = json.decode(response.body)
    return decoded.jobs or {}
end

local function complete_job(job, status, result_text)
    local response = http.post(BASE_URL .. '?complete=1', json.encode({
        job_id = job.id,
        status = status,
        result_text = result_text,
    }), {
        ['Content-Type'] = 'application/json',
        ['Authorization'] = 'Bearer ' .. BRIDGE_SECRET,
    })

    if response.status ~= 200 then
        print('Job completion failed: ' .. response.status .. ' ' .. response.body)
    end
end

local function process_jobs()
    for _, job in ipairs(poll_jobs()) do
        -- Replace with your own dispatcher per job.type
        print('Executing job ' .. job.id .. ' of type ' .. job.type)
        local success, message = dispatch_job(job)
        if success then
            complete_job(job, 'ok', message or 'Job executed successfully')
        else
            complete_job(job, 'error', message or 'Job failed to execute')
        end
    end
end

-- Example: poll every minute
addEvent(process_jobs, 60 * 1000)

-- Replace this stub with your own job handlers
function dispatch_job(job)
    -- return true/false and an optional message
    return true, 'Job executed successfully'
end
```

Remember to replace the placeholders with the actual secrets and HTTP helper
implementations that are available in your environment.
