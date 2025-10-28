if rawget(_G, 'NX_INSTANCE') then return NX_INSTANCE end
local M = {}
local function Pos(t) return Position(t.x, t.y, t.z) end

function M.teleportInto(id, player, overridePos)
  local spec = Game.getInstanceSpec(id)
  if not spec then return false, "instance missing" end
  local dst = overridePos and Pos(overridePos) or Pos(spec.spawn)
  local ok, err = Game.transferPlayerToInstance(player, id, dst, false)
  return ok, err
end

function M.getActiveInstances()
  return Game.listActiveInstances()
end

_G.NX_INSTANCE = M
return M
