// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#include "otpch.h"

#include "monster/Rank.hpp"

#include <algorithm>
#include <cctype>
#include <cmath>
#include <fstream>
#include <random>
#include <utility>

#include "tools.h"
#include "thirdparty/json.hpp"

namespace {
static constexpr const char* kTierNames[RankCount] = {
        "F",
        "E",
        "D",
        "C",
        "B",
        "A",
        "S",
        "SS",
        "SSS",
        "SSSS",
        "SSSSS",
        "SSSSSS",
};

static int32_t clampInt(int32_t value, int32_t minValue, int32_t maxValue)
{
        return std::max(minValue, std::min(maxValue, value));
}

static double clampDouble(double value, double minValue, double maxValue)
{
        return std::max(minValue, std::min(maxValue, value));
}

static size_t tierToIndex(RankTier tier)
{
        if (tier == RankTier::None) {
                return RankCount;
        }
        return static_cast<size_t>(tier);
}

static std::string toLowerCopy(const std::string& input)
{
        std::string result = input;
        std::transform(result.begin(), result.end(), result.begin(), [](unsigned char c) { return static_cast<char>(std::tolower(c)); });
        return result;
}
} // namespace

RankSystem& RankSystem::get()
{
        static RankSystem instance;
        return instance;
}

bool RankSystem::loadFromJson(const std::string& path, std::string& err)
{
        std::ifstream input(path);
        if (!input.is_open()) {
                err = "Could not open rank config: " + path;
                return false;
        }

        nlohmann::json document;
        try {
                input >> document;
        } catch (const std::exception& e) {
                err = std::string("Failed to parse rank config: ") + e.what();
                return false;
        }

        if (!document.is_object()) {
                err = "Rank config root must be an object";
                return false;
        }

        RankConfig newCfg;
        newCfg.enabled = document.value("enabled", false);
        newCfg.pressureDecayPerMinute = clampDouble(document.value("pressureDecayPerMinute", 0.99), 0.0, 1.0);
        newCfg.biasScale = clampDouble(document.value("biasScale", 0.5), 0.0, 10.0);

        if (const auto orderIt = document.find("order"); orderIt != document.end() && orderIt->is_array()) {
                size_t index = 0;
                for (const auto& entry : *orderIt) {
                        if (!entry.is_object()) {
                                continue;
                        }
                        if (index >= RankCount) {
                                break;
                        }

                        RankDef def;
                        def.name = entry.value("name", std::string{});
                        if (def.name.empty()) {
                                def.name = kTierNames[index];
                        }

                        if (const auto scalarIt = entry.find("s"); scalarIt != entry.end() && scalarIt->is_object()) {
                                def.s.hp = clampDouble(scalarIt->value("hp", def.s.hp), 0.01, 100.0);
                                def.s.dmg = clampDouble(scalarIt->value("dmg", def.s.dmg), 0.01, 100.0);
                                def.s.mit = clampDouble(scalarIt->value("mit", def.s.mit), 0.0, 0.80);
                                def.s.xp = clampDouble(scalarIt->value("xp", def.s.xp), 0.0, 100.0);
                                def.s.lootMult = clampDouble(scalarIt->value("lootMult", def.s.lootMult), 0.0, 100.0);
                                def.s.aiCdMult = clampDouble(scalarIt->value("aiCdMult", def.s.aiCdMult), 0.01, 100.0);
                                def.s.speedDelta = clampInt(scalarIt->value("speedDelta", def.s.speedDelta), -1000, 1000);
                                def.s.resist = clampInt(scalarIt->value("resist", def.s.resist), 0, 50);
                                def.s.extraRolls = static_cast<uint8_t>(clampInt(scalarIt->value("extraRolls", def.s.extraRolls), 0, 255));
                                def.s.spellUnlock = static_cast<uint8_t>(clampInt(scalarIt->value("spellUnlock", def.s.spellUnlock), 0, 255));
                        }

                        newCfg.order.emplace_back(std::move(def));
                        ++index;
                }
        }

        if (const auto weightsIt = document.find("globalWeights"); weightsIt != document.end() && weightsIt->is_object()) {
                for (auto iter = weightsIt->cbegin(); iter != weightsIt->cend(); ++iter) {
                        if (!iter.value().is_number_integer()) {
                                continue;
                        }
                        const int64_t raw = iter.value().get<int64_t>();
                        if (raw <= 0) {
                                continue;
                        }
                        const uint32_t clamped = static_cast<uint32_t>(std::min<int64_t>(raw, std::numeric_limits<uint32_t>::max()));
                        newCfg.globalWeights.emplace(iter.key(), clamped);
                }
        }

        if (const auto floorIt = document.find("floorRules"); floorIt != document.end() && floorIt->is_array()) {
                for (const auto& entry : *floorIt) {
                        if (!entry.is_object()) {
                                continue;
                        }
                        RankConfig::FloorRule rule;
                        rule.zGte = entry.value("zGte", rule.zGte);
                        rule.zLte = entry.value("zLte", rule.zLte);
                        rule.offset = entry.value("offset", rule.offset);
                        if (rule.zGte > rule.zLte) {
                                std::swap(rule.zGte, rule.zLte);
                        }
                        newCfg.floorRules.emplace_back(rule);
                }
        }

        if (const auto instIt = document.find("instanceRules"); instIt != document.end() && instIt->is_array()) {
                for (const auto& entry : *instIt) {
                        if (!entry.is_object()) {
                                continue;
                        }
                        RankConfig::InstanceRule rule;
                        rule.tierGte = entry.value("tierGte", rule.tierGte);
                        rule.hard = entry.value("hard", rule.hard);
                        rule.permadeath = entry.value("permadeath", rule.permadeath);
                        rule.offset = entry.value("offset", rule.offset);
                        newCfg.instanceRules.emplace_back(rule);
                }
        }

        if (const auto intensityIt = document.find("intensityPerKillByRank"); intensityIt != document.end() && intensityIt->is_object()) {
                for (auto iter = intensityIt->cbegin(); iter != intensityIt->cend(); ++iter) {
                        if (!iter.value().is_number()) {
                                continue;
                        }
                        newCfg.intensityPerKillByRank.emplace(iter.key(), clampDouble(iter.value().get<double>(), 0.0, 1000.0));
                }
        }

        if (newCfg.order.empty()) {
                for (size_t i = 0; i < RankCount; ++i) {
                        RankDef def;
                        def.name = kTierNames[i];
                        newCfg.order.emplace_back(std::move(def));
                }
        }

        cfg = std::move(newCfg);

        nameToTier.clear();
        weightTable.clear();
        weightTotal = 0.0;

        for (size_t index = 0; index < cfg.order.size(); ++index) {
                const RankDef& def = cfg.order[index];
                nameToTier.emplace(toLowerCopy(def.name), static_cast<RankTier>(index));
        }

        for (const auto& [name, weight] : cfg.globalWeights) {
                if (weight <= 0) {
                        continue;
                }

                auto lowerName = toLowerCopy(name);
                auto tierIt = nameToTier.find(lowerName);
                if (tierIt == nameToTier.end()) {
                        continue;
                }

                WeightEntry entry;
                entry.tier = tierIt->second;
                entry.weight = static_cast<double>(weight);
                weightTable.emplace_back(entry);
                weightTotal += entry.weight;
        }

        if (weightTable.empty()) {
                weightTable.push_back({RankTier::F, 1.0});
                weightTotal = 1.0;
        }

        return true;
}

RankTier RankSystem::pickBaseTier(const std::string& /*monsterKey*/) const
{
        if (!cfg.enabled || weightTable.empty() || weightTotal <= 0.0) {
                return RankTier::F;
        }

        auto& rng = getRandomGenerator();
        std::uniform_real_distribution<double> dist(0.0, weightTotal);
        double value = dist(rng);
        double cumulative = 0.0;
        for (const auto& entry : weightTable) {
                cumulative += entry.weight;
                if (value <= cumulative) {
                        return entry.tier;
                }
        }

        return weightTable.back().tier;
}

RankTier RankSystem::clampedAdvance(RankTier base, int offset) const
{
        if (cfg.order.empty()) {
                return RankTier::F;
        }

        size_t index = tierToIndex(base);
        if (index >= cfg.order.size()) {
                index = 0;
        }

        int32_t newIndex = static_cast<int32_t>(index) + offset;
        newIndex = clampInt(newIndex, 0, static_cast<int32_t>(cfg.order.size() - 1));
        return static_cast<RankTier>(newIndex);
}

int RankSystem::biasToOffset(double bias) const
{
        if (bias <= 0.0 || cfg.biasScale <= 0.0) {
                return 0;
        }

        double scaled = bias / cfg.biasScale;
        if (scaled <= 0.0) {
                return 0;
        }
        return std::clamp(static_cast<int>(std::floor(scaled + 1e-9)), 0, static_cast<int>(RankCount));
}

void RankSystem::applyScalars(Monster& /*m*/, RankTier /*tier*/) const
{
        // Will be populated in a later step when monster rank attributes are introduced.
}

const RankDef* RankSystem::def(RankTier tier) const
{
        size_t index = tierToIndex(tier);
        return defByIndex(index);
}

std::optional<RankTier> RankSystem::parseTier(const std::string& name) const
{
        if (name.empty()) {
                return std::nullopt;
        }

        auto lower = toLowerCopy(name);
        auto it = nameToTier.find(lower);
        if (it == nameToTier.end()) {
                return std::nullopt;
        }
        return it->second;
}

const char* RankSystem::toString(RankTier tier) const
{
        if (const RankDef* defPtr = def(tier)) {
                return defPtr->name.c_str();
        }

        size_t index = tierToIndex(tier);
        if (index < RankCount) {
                return kTierNames[index];
        }
        return "None";
}

const RankDef* RankSystem::defByIndex(size_t index) const
{
        if (index >= cfg.order.size()) {
                return nullptr;
        }
        return &cfg.order[index];
}

