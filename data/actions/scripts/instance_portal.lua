local cfgs = {
        ember_catacomb = {
                name = "Ember Catacomb",
                durationSeconds = 30 * 60,
                warnAt = {5 * 60, 60},
                scaling = {exp = 1.3, loot = 1.2, hp = 1.25, dmg = 1.2, armor = 1.1},
                bossNames = {"Lava Tyrant"},
                entryPos = {x = 1000, y = 1005, z = 7},
                exitPos = {x = 1002, y = 1002, z = 7},
                partyOnly = true,
                minLevel = 60,
                cooldownSeconds = 3600,
        },
}

function onUse(player, item, fromPosition, target, toPosition, isHotkey)
        local key = item:getAttribute(ITEM_ATTRIBUTE_DESCRIPTION) or "ember_catacomb"
        local cfg = cfgs[key]
        if not cfg then
                player:sendTextMessage(MESSAGE_STATUS_SMALL, "Portal misconfigured.")
                return true
        end

        local uid = createInstance(cfg)
        if not uid or uid == 0 then
                player:sendTextMessage(MESSAGE_STATUS_SMALL, "Failed to create instance.")
                return true
        end

        if cfg.partyOnly then
                if not bindParty(uid, player) then
                        player:sendTextMessage(MESSAGE_STATUS_SMALL, "Party requirements not met.")
                        return true
                end
        else
                if not bindPlayer(uid, player) then
                        player:sendTextMessage(MESSAGE_STATUS_SMALL, "Entry requirements not met.")
                        return true
                end
        end

        teleportInto(uid, player)
        player:sendTextMessage(MESSAGE_EVENT_ADVANCE, "You feel the air shift as reality folds around your party…")
        return true
end
