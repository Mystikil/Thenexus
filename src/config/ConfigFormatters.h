#pragma once

#include <string>
#include <string_view>
#include <type_traits>

#include <fmt/format.h>

#include "configmanager.h"

namespace cfg_cast {

// boolean
template <typename T>
inline bool as_bool(const T& v) {
        if constexpr (!std::is_enum_v<T> && requires { static_cast<bool>(v); }) {
                return static_cast<bool>(v);
        } else if constexpr (requires { v.get(); }) {
                return static_cast<bool>(v.get());
        } else if constexpr (requires { v.value(); }) {
                return static_cast<bool>(v.value());
        } else if constexpr (requires { v.value; }) {
                return static_cast<bool>(v.value);
        } else {
                static_assert(std::is_same_v<T, void>, "Unsupported boolean config wrapper type");
                return false;
        }
}

// integer -> long long
template <typename T>
inline long long as_ll(const T& v) {
        if constexpr (!std::is_enum_v<T> && requires { static_cast<long long>(v); }) {
                return static_cast<long long>(v);
        } else if constexpr (requires { v.get(); }) {
                return static_cast<long long>(v.get());
        } else if constexpr (requires { v.value(); }) {
                return static_cast<long long>(v.value());
        } else if constexpr (requires { v.value; }) {
                return static_cast<long long>(v.value);
        } else {
                static_assert(std::is_same_v<T, void>, "Unsupported integer config wrapper type");
                return 0;
        }
}

// string -> std::string_view
template <typename T>
inline std::string_view as_sv(const T& v) {
        if constexpr (requires { std::string_view{v}; }) {
                return std::string_view{v};
        } else if constexpr (requires { v.c_str(); }) {
                return std::string_view{v.c_str()};
        } else if constexpr (requires { v.str(); }) {
                static thread_local std::string buf;
                buf = v.str();
                return std::string_view{buf};
        } else if constexpr (requires { v.get(); }) {
                static thread_local std::string buf;
                buf = v.get();
                return std::string_view{buf};
        } else if constexpr (requires { v.value(); }) {
                static thread_local std::string buf;
                buf = v.value();
                return std::string_view{buf};
        } else if constexpr (requires { v.value; }) {
                static thread_local std::string buf;
                buf = v.value;
                return std::string_view{buf};
        } else {
                static_assert(std::is_same_v<T, void>, "Unsupported string config wrapper type");
                return {};
        }
}

} // namespace cfg_cast

namespace fmt {

template <>
struct formatter<ConfigManager::boolean_config_t> : formatter<string_view> {
        template <typename FormatContext>
        auto format(const ConfigManager::boolean_config_t& v, FormatContext& ctx) const {
                return formatter<string_view>::format(cfg_cast::as_bool(v) ? "true" : "false", ctx);
        }
};

template <>
struct formatter<ConfigManager::integer_config_t> : formatter<long long> {
        template <typename FormatContext>
        auto format(const ConfigManager::integer_config_t& v, FormatContext& ctx) const {
                return formatter<long long>::format(cfg_cast::as_ll(v), ctx);
        }
};

template <>
struct formatter<ConfigManager::string_config_t> : formatter<string_view> {
        template <typename FormatContext>
        auto format(const ConfigManager::string_config_t& v, FormatContext& ctx) const {
                return formatter<string_view>::format(cfg_cast::as_sv(v), ctx);
        }
};

} // namespace fmt

