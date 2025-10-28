local NX_INSTANCE = dofile('data/lib/instance.lua')
function onSay(player, words, param)
local cmd, arg = param:match("^(%S+)%s*(.*)$")
if cmd == "list" then
local configured = Game.listInstances()
local active = NX_INSTANCE.getActiveInstances()
player:sendTextMessage(MESSAGE_INFO_DESCR, "Configured instances:")
for _, s in ipairs(configured) do
player:sendTextMessage(MESSAGE_INFO_DESCR, string.format("- %d: %s", s.id, s.name or ""))
end
local act = {}
for i=1,#active do table.insert(act, tostring(active[i])) end
player:sendTextMessage(MESSAGE_INFO_DESCR, "Active: " .. (#act>0 and table.concat(act, ", ") or "none"))
elseif cmd == "info" then
local id = tonumber(arg) or 0
local spec = Game.getInstanceSpec(id)
if not spec then
player:sendTextMessage(MESSAGE_INFO_DESCR, "No such instance: "..id)
return false
end
player:sendTextMessage(MESSAGE_INFO_DESCR, string.format("[%d] %s otbm=%s spawn=(%d,%d,%d)",
spec.id, spec.name, spec.otbm, spec.spawn.x, spec.spawn.y, spec.spawn.z))
else
player:sendTextMessage(MESSAGE_INFO_DESCR, "!inst list | !inst info <id>")
end
return false
end
