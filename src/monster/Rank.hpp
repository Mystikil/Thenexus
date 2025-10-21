// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#pragma once

#include <cstdint>
#include <limits>
#include <optional>
#include <string>
#include <unordered_map>
#include <vector>

class Monster;

enum class RankTier : uint8_t {
        F,
        E,
        D,
        C,
        B,
        A,
        S,
        SS,
        SSS,
        SSSS,
        SSSSS,
        SSSSSS,
        None,
};

constexpr size_t RankCount = 12;

struct RankScalars {
        double hp = 1.0;
        double dmg = 1.0;
        double mit = 0.0;
        double xp = 1.0;
        double lootMult = 1.0;
        double aiCdMult = 1.0;
        int32_t speedDelta = 0;
        int32_t resist = 0;
        uint8_t extraRolls = 0;
        uint8_t spellUnlock = 0;
};

struct RankDef {
        std::string name;
        RankScalars s;
};

struct RankConfig {
        bool enabled = false;
        std::vector<RankDef> order;
        std::unordered_map<std::string, uint32_t> globalWeights;

        struct FloorRule {
                int zGte = std::numeric_limits<int>::min();
                int zLte = std::numeric_limits<int>::max();
                int offset = 0;
        };

        struct InstanceRule {
                int tierGte = 0;
                bool hard = false;
                bool permadeath = false;
                int offset = 0;
        };

        std::vector<FloorRule> floorRules;
        std::vector<InstanceRule> instanceRules;

        double pressureDecayPerMinute = 0.99;
        double biasScale = 0.5;
        std::unordered_map<std::string, double> intensityPerKillByRank;
};

class RankSystem {
        public:
                static RankSystem& get();

                bool loadFromJson(const std::string& path, std::string& err);
                bool isEnabled() const {
                        return cfg.enabled;
                }

                RankTier pickBaseTier(const std::string& monsterKey) const;
                RankTier clampedAdvance(RankTier base, int offset) const;
                int biasToOffset(double bias) const;

                void applyScalars(Monster& m, RankTier tier) const;

                const RankDef* def(RankTier tier) const;
                std::optional<RankTier> parseTier(const std::string& name) const;
                const char* toString(RankTier tier) const;

                const RankConfig& config() const {
                        return cfg;
                }

        private:
                RankSystem() = default;

                struct WeightEntry {
                        RankTier tier = RankTier::F;
                        double weight = 0.0;
                };

                const RankDef* defByIndex(size_t index) const;

                RankConfig cfg;
                std::unordered_map<std::string, RankTier> nameToTier;
                std::vector<WeightEntry> weightTable;
                double weightTotal = 0.0;
};

