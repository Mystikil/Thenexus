// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#include "otpch.h"

#include "InstanceManager.h"

#include <algorithm>
#include <fstream>
#include <iostream>
#include <utility>

#include "../iomap.h"
#include "../map.h"
#include "../thirdparty/json.hpp"

namespace {
        using json = nlohmann::json;

        Position parsePosition(const json& value, const Position& fallback = Position{})
        {
                Position result = fallback;
                if (!value.is_object()) {
                        return result;
                }

                result.x = value.value("x", static_cast<uint16_t>(fallback.x));
                result.y = value.value("y", static_cast<uint16_t>(fallback.y));
                result.z = value.value("z", static_cast<uint8_t>(fallback.z));
                return result;
        }

        InstanceRules parseRules(const json& value, const InstanceRules& defaults = InstanceRules{})
        {
                InstanceRules rules = defaults;
                if (!value.is_object()) {
                        return rules;
                }

                rules.expRate = value.value("expRate", rules.expRate);
                rules.lootRate = value.value("lootRate", rules.lootRate);
                rules.permadeath = value.value("permadeath", rules.permadeath);
                rules.bindOnEntry = value.value("bindOnEntry", rules.bindOnEntry);
                rules.pvpMode = value.value("pvpMode", rules.pvpMode);
                rules.persistent = value.value("persistent", rules.persistent);
                rules.unloadGraceSeconds = value.value("unloadGraceSeconds", rules.unloadGraceSeconds);
                return rules;
        }
}

InstanceManager& InstanceManager::get()
{
        return g_instances;
}

bool InstanceManager::loadConfig(const std::string& path)
{
        std::ifstream input(path);
        if (!input.is_open()) {
                std::cout << "[Error - InstanceManager::loadConfig] Failed to open " << path << '\n';
                return false;
        }

        json data;
        try {
                input >> data;
        } catch (const std::exception& e) {
                std::cout << "[Error - InstanceManager::loadConfig] Failed to parse JSON: " << e.what() << '\n';
                return false;
        }

        const json* instances = nullptr;
        if (data.is_array()) {
                instances = &data;
        } else {
                auto it = data.find("instances");
                if (it != data.end()) {
                        instances = &(*it);
                }
        }

        if (!instances || !instances->is_array()) {
                std::cout << "[Error - InstanceManager::loadConfig] No instances array in " << path << '\n';
                return false;
        }

        std::vector<InstanceSpec> loaded;
        loaded.reserve(instances->size());

        for (const auto& entry : *instances) {
                if (!entry.is_object()) {
                        continue;
                }

                InstanceSpec spec;
                spec.id = static_cast<InstanceId>(entry.value("id", static_cast<uint32_t>(0)));
                spec.name = entry.value("name", std::string{});
                spec.otbm = entry.value("otbm", std::string{});
                spec.spawn = parsePosition(entry.value("spawn", json::object()), spec.spawn);
                spec.rules = parseRules(entry.value("rules", json::object()), spec.rules);
                spec.persistent = entry.value("persistent", spec.persistent);

                if (spec.otbm.empty()) {
                        std::cout << "[Warning - InstanceManager::loadConfig] Instance " << spec.id << " missing otbm path\n";
                        continue;
                }

                loaded.emplace_back(std::move(spec));
        }

        specs_ = std::move(loaded);
        return true;
}

bool InstanceManager::ensureLoaded(InstanceId id)
{
        if (maps_.find(id) != maps_.end()) {
                return true;
        }

        const InstanceSpec* spec = getSpec(id);
        if (!spec) {
                return false;
        }

        auto map = std::make_unique<Map>();
        map->setInstanceId(id);

        if (!IOMap::loadInto(spec->otbm, *map)) {
                return false;
        }

        maps_[id] = std::move(map);
        playerCounts_.try_emplace(id, 0);
        return true;
}

Map* InstanceManager::getMap(InstanceId id) const
{
        auto it = maps_.find(id);
        if (it == maps_.end()) {
                return nullptr;
        }
        return it->second.get();
}

const InstanceSpec* InstanceManager::getSpec(InstanceId id) const
{
        auto it = std::find_if(specs_.begin(), specs_.end(), [id](const InstanceSpec& spec) {
                return spec.id == id;
        });

        if (it == specs_.end()) {
                return nullptr;
        }
        return &*it;
}

std::vector<InstanceId> InstanceManager::active() const
{
        std::vector<InstanceId> ids;
        ids.reserve(maps_.size());
        for (const auto& entry : maps_) {
                ids.push_back(entry.first);
        }
        return ids;
}

void InstanceManager::heartbeat()
{
        std::vector<InstanceId> toClose;
        toClose.reserve(maps_.size());

        for (const auto& mapEntry : maps_) {
                const InstanceId id = mapEntry.first;
                const InstanceSpec* spec = getSpec(id);
                if (!spec || spec->persistent) {
                        continue;
                }

                if (playerCounts_.find(id) != playerCounts_.end()) {
                        continue;
                }

                toClose.push_back(id);
        }

        for (InstanceId id : toClose) {
                closeInstance(id);
        }
}

void InstanceManager::onPlayerEnter(InstanceId id)
{
        ++playerCounts_[id];
}

void InstanceManager::onPlayerLeave(InstanceId id)
{
        auto it = playerCounts_.find(id);
        if (it == playerCounts_.end()) {
                return;
        }

        if (it->second > 1) {
                --it->second;
                return;
        }

        playerCounts_.erase(it);
}

bool InstanceManager::closeInstance(InstanceId id)
{
        auto mapIt = maps_.find(id);
        if (mapIt == maps_.end()) {
                return false;
        }

        auto countIt = playerCounts_.find(id);
        if (countIt != playerCounts_.end() && countIt->second > 0) {
                return false;
        }

        maps_.erase(mapIt);
        if (countIt != playerCounts_.end()) {
                playerCounts_.erase(countIt);
        }
        return true;
}

InstanceManager g_instances;

