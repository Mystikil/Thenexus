# TFS Bridge Lua Examples

The snippets below illustrate how to publish webhook events and process bridge jobs
from an OTServ/TFS Lua environment. Adapt the HTTP helper functions to the
facilities that your server distribution provides.

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

local function poll_jobs()
    local response = http.get('https://example.com/api/jobs_pull.php?limit=5', {
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
    local response = http.post('https://example.com/api/jobs_pull.php?complete=1', json.encode({
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
        complete_job(job, 'ok', 'Job executed successfully')
    end
end

-- Example: poll every minute
addEvent(process_jobs, 60 * 1000)
```

Remember to replace the placeholders with the actual secrets and HTTP helper
implementations that are available in your environment.
