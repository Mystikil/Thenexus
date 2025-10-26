-- Dungeon Browser (skeleton) for OTClient
-- Shows dungeons, discovery-gated availability, with TopMenu toggle and hotkey.

local M = {}
modules.game_dungeons = M

M.onShow = {}
M.onHide = {}
M.onVisibilityChange = {}

local ui = {}
local widgets = {}
local state = {
  selectedId = nil,
  -- Discovered set; "starter" is always available by rule
  discovered = {},
  -- meta[id] = { name=..., level=..., desc=... }
  meta = {},
  -- All known dungeons for listing (id -> baseline meta)
  catalog = {
    starter = { name = "Starter Dungeon", level = 1, desc = "Your very first delve. Always available." },
    goblin_caves = { name = "Goblin Caves", level = 5, desc = "Crumbling tunnels packed with goblins." },
    crystal_depths = { name = "Crystal Depths", level = 12, desc = "Glittering caverns with sharp surprises." },
    spider_hollows = { name = "Spider Hollows", level = 8, desc = "Sticky webs and sneaky fangs." },
  }
}

local DUNGEON_EXT_OPCODE = 110
M.DUNGEON_EXT_OPCODE = DUNGEON_EXT_OPCODE
local SETTINGS_KEY = "dungeons/discovered"

local defaultDescription = "Explore mysterious places. This is a placeholder description."
local defaultLockedText = "Locked (Not discovered)"
local defaultAvailableText = "Available"

local function prettyName(id)
  return id:gsub("_", " "):gsub("^%l", string.upper)
end

local function isStarter(id)
  return id == "starter" or id == "beginner"
end

local function isAvailable(id)
  if isStarter(id) then
    return true
  end
  return state.discovered[id] == true
end

local function getMeta(id)
  if state.meta[id] then
    return state.meta[id]
  end
  if state.catalog[id] then
    return state.catalog[id]
  end
  return { name = prettyName(id), level = 1, desc = defaultDescription }
end

local function decodeJson(str)
  if type(str) ~= 'string' then
    return nil
  end
  local ok, result = pcall(json.decode, str)
  if not ok or type(result) ~= 'table' then
    return nil
  end
  return result
end

local function saveDiscovered()
  local discoveredList = {}
  for id, value in pairs(state.discovered) do
    if value then
      table.insert(discoveredList, id)
    end
  end
  table.sort(discoveredList)
  local ok, encoded = pcall(json.encode, discoveredList)
  if ok then
    g_settings.set(SETTINGS_KEY, encoded)
  end
end

local function loadDiscovered()
  local raw = g_settings.get(SETTINGS_KEY)
  if type(raw) ~= 'string' or raw == '' then
    return
  end
  local list = decodeJson(raw)
  if type(list) ~= 'table' then
    return
  end
  for _, id in ipairs(list) do
    if type(id) == 'string' then
      state.discovered[id] = true
    end
  end
end

local function updateDetails(id)
  if not ui.window or ui.window:isDestroyed() then
    return
  end

  if not id then
    widgets.dungeonName:setText("Select a dungeon")
    widgets.dungeonStatus:setText("—")
    widgets.dungeonLevel:setText("—")
    widgets.dungeonDescription:setText(defaultDescription)
    widgets.enterButton:setEnabled(false)
    return
  end

  local meta = getMeta(id)
  widgets.dungeonName:setText(meta.name or prettyName(id))
  local level = tonumber(meta.level) or 1
  widgets.dungeonLevel:setText(string.format("Recommended Level: %d", level))
  local available = isAvailable(id)
  widgets.dungeonStatus:setText(available and defaultAvailableText or defaultLockedText)
  widgets.dungeonDescription:setText((meta.desc and meta.desc ~= '' and meta.desc) or defaultDescription)
  widgets.enterButton:setEnabled(available)
end

local function clearList()
  if not widgets.dungeonList then
    return
  end
  widgets.dungeonList:destroyChildren()
end

local function selectDungeon(id)
  if not widgets.dungeonList then
    return
  end
  state.selectedId = id
  for _, child in ipairs(widgets.dungeonList:getChildren()) do
    child:setChecked(child.dungeonId == id)
  end
  updateDetails(id)
end

local function statusSuffix(id)
  return isAvailable(id) and " [Available]" or " [Locked]"
end

local function addListItem(id)
  if not widgets.dungeonList then
    return
  end
  local meta = getMeta(id)
  local item = g_ui.createWidget('UIButton', widgets.dungeonList)
  item.dungeonId = id
  item:setText((meta.name or prettyName(id)) .. statusSuffix(id))
  item:setCheckable(true)
  item:setFocusable(false)
  item.onClick = function()
    selectDungeon(id)
  end
  item.onDoubleClick = function()
    selectDungeon(id)
    M.tryEnterSelected()
  end
  if state.selectedId == id then
    item:setChecked(true)
  end
end

local function rebuildList()
  if not widgets.dungeonList then
    return
  end

  clearList()

  local query = ''
  if widgets.searchEdit then
    query = widgets.searchEdit:getText():lower()
  end

  local idSet = {}
  for id in pairs(state.catalog) do
    idSet[id] = true
  end
  for id in pairs(state.meta) do
    idSet[id] = true
  end
  for id in pairs(state.discovered) do
    idSet[id] = true
  end

  local ids = {}
  for id in pairs(idSet) do
    table.insert(ids, id)
  end
  table.sort(ids, function(a, b)
    if a == b then
      return false
    end
    if isStarter(a) then
      return true
    end
    if isStarter(b) then
      return false
    end
    return a < b
  end)

  for _, id in ipairs(ids) do
    local meta = getMeta(id)
    local name = (meta.name or prettyName(id)):lower()
    if query == '' or name:find(query, 1, true) then
      addListItem(id)
    end
  end

  local hasSelection = false
  for _, child in ipairs(widgets.dungeonList:getChildren()) do
    if child.dungeonId == state.selectedId then
      child:setChecked(true)
      hasSelection = true
      break
    end
  end
  if not hasSelection then
    state.selectedId = nil
  end
  updateDetails(state.selectedId)
end

local function updateMenuButton(visible)
  if ui.menuButton and not ui.menuButton:isDestroyed() then
    ui.menuButton:setOn(visible)
  end
end

local function handleVisibility(widget, visible)
  updateMenuButton(visible)
  signalcall(M.onVisibilityChange, widget, visible)
  if visible then
    signalcall(M.onShow, widget)
  else
    signalcall(M.onHide, widget)
  end
end

local function createTopMenuButton()
  if ui.menuButton and not ui.menuButton:isDestroyed() then
    ui.menuButton:destroy()
  end
  ui.menuButton = nil

  local button
  if modules.game_interface and modules.game_interface.getTopMenu then
    local topMenu = modules.game_interface.getTopMenu()
    if topMenu and topMenu.addLeftGameButton then
      button = topMenu:addLeftGameButton('dungeonButton', tr('Dungeons'), '/images/topbuttons/battle', function()
        M.toggle()
      end, false, 0)
    end
  end
  if not button and modules.client_topmenu and modules.client_topmenu.addLeftGameButton then
    button = modules.client_topmenu.addLeftGameButton('dungeonButton', tr('Dungeons'), '/images/topbuttons/battle', function()
      M.toggle()
    end, false, 0)
  end
  if button then
    button:setTooltip(tr('Dungeons (Ctrl+D)'))
    button:setOn(false)
    ui.menuButton = button
  end
end

local function onDungeonOpcode(protocol, opcode, buffer)
  if opcode ~= DUNGEON_EXT_OPCODE then
    return
  end
  local payload = buffer:decodeString()
  local tbl = decodeJson(payload)
  if type(tbl) ~= 'table' then
    g_logger.warning('[Dungeons] Received malformed payload for discovery update.')
    return
  end

  local discovered = tbl.discovered
  if type(discovered) == 'table' then
    state.discovered = {}
    for _, id in ipairs(discovered) do
      if type(id) == 'string' then
        state.discovered[id] = true
      end
    end
    saveDiscovered()
  end

  local metaBlock = tbl.meta
  if type(metaBlock) == 'table' then
    for id, meta in pairs(metaBlock) do
      if type(id) == 'string' and type(meta) == 'table' then
        local existing = getMeta(id)
        state.meta[id] = {
          name = meta.name or existing.name or prettyName(id),
          level = tonumber(meta.level) or existing.level or 1,
          desc = meta.desc or existing.desc or defaultDescription
        }
      end
    end
  end

  rebuildList()
end

local function onGameStart()
  g_game.enableExtendedOpcode(DUNGEON_EXT_OPCODE)
end

local function onGameEnd()
  if ui.window then
    ui.window:hide()
  end
end

function M.tryEnterSelected()
  local id = state.selectedId
  if not id then
    return
  end
  if not isAvailable(id) then
    displayInfoBox(tr('Dungeon Locked'), tr('You must discover this dungeon in the world before you can enter.'))
    return
  end
  local meta = getMeta(id)
  displayInfoBox(tr('Enter Dungeon'), string.format(tr("Entering '%s' not yet implemented on client."), meta.name or prettyName(id)))
end

function M.show()
  if ui.window then
    ui.window:show()
    ui.window:raise()
    ui.window:focus()
  end
end

function M.hide()
  if ui.window then
    ui.window:hide()
  end
end

function M.toggle()
  if not ui.window then
    return
  end
  if ui.window:isVisible() then
    M.hide()
  else
    M.show()
  end
end

local function bindHotkey()
  Keybind.new('Windows', 'Show/hide Dungeon Browser', 'Ctrl+D', '')
  Keybind.bind('Windows', 'Show/hide Dungeon Browser', {
    {
      type = KEY_DOWN,
      callback = function()
        M.toggle()
      end
    }
  })
end

local function unbindHotkey()
  Keybind.delete('Windows', 'Show/hide Dungeon Browser')
end

function init()
  ui.window = g_ui.displayUI('dungeon_window.otui')
  ui.window:hide()
  ui.window.onVisibilityChange = handleVisibility

  widgets.dungeonList = ui.window:recursiveGetChildById('dungeonList')
  widgets.searchEdit = ui.window:recursiveGetChildById('searchEdit')
  widgets.dungeonName = ui.window:recursiveGetChildById('dungeonName')
  widgets.dungeonStatus = ui.window:recursiveGetChildById('dungeonStatus')
  widgets.dungeonLevel = ui.window:recursiveGetChildById('dungeonLevel')
  widgets.dungeonDescription = ui.window:recursiveGetChildById('dungeonDescription')
  widgets.enterButton = ui.window:recursiveGetChildById('enterButton')

  if widgets.searchEdit then
    widgets.searchEdit.onTextChange = function()
      rebuildList()
    end
  end

  loadDiscovered()

  if next(state.discovered) == nil then
    state.discovered = { goblin_caves = true }
  end

  rebuildList()

  createTopMenuButton()
  bindHotkey()

  connect(g_game, { onGameStart = onGameStart, onGameEnd = onGameEnd })
  ProtocolGame.registerExtendedOpcode(DUNGEON_EXT_OPCODE, onDungeonOpcode)

  if g_game.isOnline() then
    onGameStart()
  end
end

function terminate()
  ProtocolGame.unregisterExtendedOpcode(DUNGEON_EXT_OPCODE)
  disconnect(g_game, { onGameStart = onGameStart, onGameEnd = onGameEnd })
  unbindHotkey()

  if ui.menuButton and not ui.menuButton:isDestroyed() then
    ui.menuButton:destroy()
  end
  ui.menuButton = nil

  if ui.window then
    ui.window:destroy()
  end
  ui.window = nil
  widgets = {}

  state.selectedId = nil
end

return M
