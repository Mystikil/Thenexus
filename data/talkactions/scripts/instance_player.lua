local NX_INSTANCE = dofile('data/lib/instance.lua')
function onSay(player, words, param)
local id = tonumber(param)
if not id then
player:sendTextMessage(MESSAGE_INFO_DESCR, "Usage: /instanceid <id>")
return false
end
local spec = Game.getInstanceSpec(id)
if not spec then
player:sendTextMessage(MESSAGE_INFO_DESCR, "No such instance: "..id)
return false
end
local ok, err = NX_INSTANCE.teleportInto(id, player)
if not ok then
player:sendTextMessage(MESSAGE_INFO_DESCR, "Transfer failed: "..(err or "?"))
end
return false
end
