local rarityShaders = {
  ["common"] = "grayscale",
  ["uncommon"] = "outline",
  ["rare"] = "pulse",
  ["epic"] = "party",
  ["legendary"] = "bloom",
  ["mythic"] = "zomg",
  ["ancient"] = "oldtv",
  ["divine"] = "radialblur",
  ["void"] = "snow"
}

for rarity, shaderName in pairs(rarityShaders) do
  local path = string.format("/modules/game_shaders/shaders/fragment/%s.frag", shaderName)
  if g_resources.fileExists(path) then
    g_shaders.createFragmentShader(shaderName, path)
  else
    warn("Missing shader file: " .. path)
  end
end

function applyRarityShader(item)
  if item and item.getAttribute then
    local rarity = item:getAttribute("rarity")
    if rarity then
      local shader = rarityShaders[rarity:lower()]
      if shader then
        item:setShader(shader)
      end
    end
  end
end

-- Optional: hook into inventory
connect(LocalPlayer, {
  onInventoryChange = function(player, slot, item)
    if item then applyRarityShader(item) end
  end
})
        g_shaders.setShaderDrawViewportEdge(opts.drawViewportEdge or false)
        g_shaders.setShaderDrawColor(opts.drawColor or true)
        g_shaders.setShaderUseFramebuffer(opts.useFramebuffer or false)

        if method then
            method(opts.name, fragmentShaderPath)
        end
    end
end   