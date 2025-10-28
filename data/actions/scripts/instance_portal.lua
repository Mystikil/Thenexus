local NX_INSTANCE = dofile('data/lib/instance.lua')

local cfg = {
        instanceId = 1,
        entryPos = { x = 200, y = 200, z = 7 },
}

function onUse(player, item, fromPos, target, toPos, isHotkey)
        local ok, err = NX_INSTANCE.teleportInto(cfg.instanceId, player, cfg.entryPos)
        if not ok then
                player:sendTextMessage(MESSAGE_STATUS_CONSOLE_BLUE, "Portal failed: " .. (err or "?"))
        end
        return true
end
