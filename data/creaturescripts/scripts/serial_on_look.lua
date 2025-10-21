function onLook(player, thing, position, distance)
        if not player then
                return true
        end

        local group = player:getGroup() and player:getGroup():getId() or 1
        if group >= 3 and thing and thing.isItem and thing:isItem() then
                local desc = thing:getAttribute(ITEM_ATTRIBUTE_DESCRIPTION) or ""
                if desc ~= "" then
                        player:sendTextMessage(MESSAGE_INFO_DESCR, "Serial: " .. desc)
                end
        end
        return true
end
