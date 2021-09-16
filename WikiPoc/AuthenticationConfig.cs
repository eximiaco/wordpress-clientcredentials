// Copyright (c) Microsoft Corporation. All rights reserved.
// Licensed under the MIT License.

using Microsoft.Extensions.Configuration;
using System;
using System.Globalization;
using System.IO;

namespace WikiPoc
{
    public class AuthenticationConfig
    {
        public string WordpressUri { get; set; }
        public string Instance { get; set; }
        public string Audience { get; set; }
        public string Tenant { get; set; }
        public string ClientId { get; set; }
        public string Authority
        {
            get
            {
                return String.Format(CultureInfo.InvariantCulture, Instance, Tenant);
            }
        }
        public string ClientSecret { get; set; }
        
        public static AuthenticationConfig ReadFromJsonFile(string path)
        {
            IConfigurationRoot Configuration;

            var builder = new ConfigurationBuilder()
             .SetBasePath(Directory.GetCurrentDirectory())
            .AddJsonFile(path);

            Configuration = builder.Build();

            var config = new AuthenticationConfig();
            Configuration.Bind("AzureAD", config);

            return config;
        }
    }
}
