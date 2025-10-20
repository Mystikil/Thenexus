return {
        enabled = true,

        -- Optional: add a short prefix to help visually parse serials
        serial_prefix = "NX-",

        -- Exclude noisy items from serialization:
        exclude = {
                stackable = true,       -- true = skip stackables (e.g., gold coins)
                fluid = true,           -- skip fluid containers
                corpse = true,          -- skip dead bodies themselves
        },

        -- Optional: per-itemid blacklist if needed
        blacklist_itemids = {
                -- [3031] = true, -- gold coin
        },
}
