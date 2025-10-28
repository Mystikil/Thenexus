// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#ifndef FS_INSTANCEMANAGER_H
#define FS_INSTANCEMANAGER_H

#include <memory>
#include <string>
#include <unordered_map>
#include <vector>

#include "../definitions.h"
#include "../position.h"

class Map;

struct InstanceRules {
        float expRate = 1.0f;
        float lootRate = 1.0f;
        bool permadeath = false;
        bool bindOnEntry = false;
        std::string pvpMode = "optional";
        bool persistent = true;
        int unloadGraceSeconds = 60;
};

struct InstanceSpec {
        InstanceId id = 0;
        std::string name;
        std::string otbm;
        Position spawn;
        InstanceRules rules;
        bool persistent = true;
};

class InstanceManager {
        public:
                bool loadConfig(const std::string& path);
                bool ensureLoaded(InstanceId id);
                Map* getMap(InstanceId id) const;
                const InstanceSpec* getSpec(InstanceId id) const;
                const std::vector<InstanceSpec>& configured() const { return specs_; }
                std::vector<InstanceId> active() const;
                void onPlayerEnter(InstanceId id);
                void onPlayerLeave(InstanceId id);
                bool closeInstance(InstanceId id);

        private:
                std::unordered_map<InstanceId, std::unique_ptr<Map>> maps_;
                std::unordered_map<InstanceId, int> playerCounts_;
                std::vector<InstanceSpec> specs_;
};

extern InstanceManager g_instances;

#endif // FS_INSTANCEMANAGER_H
