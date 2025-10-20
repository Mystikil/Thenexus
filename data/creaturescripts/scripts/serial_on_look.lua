dofile('data/lib/serialization.lua')

function onLook(player, thing, position, distance)
        local description = thing:getDescription(distance)

        if thing:isItem() then
                description = Serialization.injectSerial(description, thing, player)
        end

        player:sendTextMessage(MESSAGE_INFO_DESCR, description)
        return false
end
