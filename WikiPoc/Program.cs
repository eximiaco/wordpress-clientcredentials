using System;
using System.Diagnostics;
using System.Linq;
using System.Net.Mime;
using System.Threading.Tasks;
using Microsoft.Identity.Client;

namespace WikiPoc
{
    class Program
    {
        static async Task Main(string[] args)
        {
            var config = AuthenticationConfig.ReadFromJsonFile("appsettings.json");
            var token = await GetTokenAsync(config);

            if (PromptToWordpress())
                OpenWordpress(config, token);
        }
        
        private static async Task<string> GetTokenAsync(AuthenticationConfig config)
        {
            var app = ConfidentialClientApplicationBuilder.Create(config.ClientId)
                .WithClientSecret(config.ClientSecret)
                .WithAuthority(new Uri(config.Authority))
                .Build();

            var scopes = new[] { $"{config.Audience}.default" };

            AuthenticationResult result = null;
            try
            {
                result = await app.AcquireTokenForClient(scopes)
                    .ExecuteAsync();
                Console.ForegroundColor = ConsoleColor.Green;
                Console.WriteLine("Token acquired");
                Console.ResetColor();
            }
            catch (MsalServiceException ex) when (ex.Message.Contains("AADSTS70011"))
            {
                // Invalid scope. The scope has to be of the form "https://resourceurl/.default"
                // Mitigation: change the scope to be as expected
                Console.ForegroundColor = ConsoleColor.Red;
                Console.WriteLine("Scope provided is not supported");
                Console.ResetColor();
            }

            return result?.AccessToken;
        }

        private static bool PromptToWordpress()
        {
            Console.Write("Open Wordpress (y/n): ");
            var key = Console.ReadKey();
            Console.WriteLine();
            return (key.Key == ConsoleKey.Y);
        }

        private static void OpenWordpress(AuthenticationConfig config, string token)
        {
            var uri = string.Format(config.WordpressUri, token);
            Console.WriteLine(uri);
                
            var p = new Process();
            p.StartInfo.FileName = "open";
            p.StartInfo.UseShellExecute = false;
            p.StartInfo.Arguments = $"{uri}";
            p.StartInfo.RedirectStandardOutput = true;
            p.StartInfo.CreateNoWindow = true;
            p.Start();
            p.WaitForExitAsync().Wait();
        }
    }
}