# Dungeon Browser (Skeleton)

- Toggle from Top Menu “Dungeons” button or `Ctrl+D`.
- Starter is always available. Others require discovery.
- Server sends discovery data via extended opcode **110** as UTF-8 JSON:
  ```json
  {
    "discovered": ["goblin_caves","crystal_depths"],
    "meta": {
      "goblin_caves": {"name":"Goblin Caves","level":5},
      "crystal_depths":{"name":"Crystal Depths","level":12}
    }
  }


Client persists discovered ids to g_settings under dungeons/discovered.

tryEnterSelected() is a placeholder for future “enter dungeon” flow.


---

### Wire into existing UI

**Add the TopMenu button only if your client doesn’t auto-discover `addLeftGameButton`:** The module already calls `getTopMenu():addLeftGameButton(...)`. If your OTClient variant requires explicit export, ensure `modules/game_interface/gameinterface.lua` exposes `getTopMenu()` (most do). If not, add:

```lua
-- in modules/game_interface/gameinterface.lua (near other exports)
function getTopMenu()
  return topMenu
end


(Do not duplicate if it already exists.)

Optional: expose a simple command to open the window

If you use a client talk command system, add:

g_game.talk("/dungeons") -- mapped in your command handler to modules.game_dungeons.toggle()

Add a button somewhere to open the dungeon window system.
