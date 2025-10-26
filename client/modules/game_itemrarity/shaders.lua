function init()
    g_shaders.createFragmentShader("rarity_epic", [[
      uniform sampler2D texture;
      void main() {
        vec4 color = texture2D(texture, gl_TexCoord[0].xy);
        color.rgb = vec3(1.0, 0.5, 0.0);
        gl_FragColor = color;
      }
    ]])
    g_shaders.setupItemShader("rarity_epic")

    g_shaders.createFragmentShader("rarity_legendary", [[
      uniform sampler2D texture;
      void main() {
        vec4 color = texture2D(texture, gl_TexCoord[0].xy);
        color.rgb = vec3(1.0, 0.8, 0.0);
        gl_FragColor = color;
      }
    ]])
    g_shaders.setupItemShader("rarity_legendary")
end

function terminate()
end
