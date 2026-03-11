<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = [
            [
                'title' => 'The Rise of Agentic AI: How Autonomous Systems Are Reshaping Enterprise',
                'slug' => 'rise-of-agentic-ai',
                'excerpt' => 'Agentic AI is moving beyond chatbots into autonomous decision-making systems that can plan, execute, and iterate — transforming how businesses operate at scale.',
                'content' => '<p>The AI landscape is undergoing a fundamental shift. While large language models captured the world\'s attention in 2024, the real revolution happening in 2025-2026 is the emergence of <strong>agentic AI</strong> — systems that don\'t just respond to prompts but autonomously plan, execute, and adapt to achieve complex goals.</p>

<h2>What Makes AI "Agentic"?</h2>
<p>Unlike traditional AI assistants that wait for instructions, agentic AI systems can break down complex objectives into sub-tasks, use tools and APIs, make decisions based on intermediate results, and course-correct when things go wrong. Think of it as the difference between a calculator and a project manager.</p>

<h2>Enterprise Adoption</h2>
<p>Companies like Salesforce, Microsoft, and Google are embedding agentic capabilities into their platforms. Salesforce\'s Agentforce, Microsoft\'s Copilot Studio, and Google\'s Vertex AI agents are enabling businesses to deploy autonomous workflows for customer service, data analysis, and software development.</p>

<h2>Key Use Cases</h2>
<ul>
<li><strong>Software Engineering:</strong> AI agents that can read codebases, plan implementations, write code, run tests, and iterate on failures</li>
<li><strong>Data Pipeline Automation:</strong> Agents that monitor data quality, detect anomalies, and self-heal broken pipelines</li>
<li><strong>Customer Operations:</strong> Multi-step resolution workflows that handle complex cases without human intervention</li>
</ul>

<h2>The Challenge Ahead</h2>
<p>The biggest hurdle isn\'t technical — it\'s trust. Organizations need robust guardrails, audit trails, and human-in-the-loop checkpoints before deploying autonomous systems in production. The companies that solve this trust equation first will have a massive competitive advantage.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Real-Time Data Pipelines: Why Batch Processing Is No Longer Enough',
                'slug' => 'real-time-data-pipelines',
                'excerpt' => 'As businesses demand instant insights, real-time streaming architectures powered by Apache Kafka, Flink, and modern lakehouse platforms are replacing traditional batch ETL workflows.',
                'content' => '<p>For decades, batch processing was the backbone of enterprise data. Run ETL jobs overnight, generate reports in the morning, make decisions by afternoon. But in a world of instant payments, real-time fraud detection, and personalized experiences, waiting hours for data is a competitive liability.</p>

<h2>The Streaming Revolution</h2>
<p>Technologies like <strong>Apache Kafka</strong>, <strong>Apache Flink</strong>, and <strong>Apache Spark Structured Streaming</strong> have matured to the point where real-time data processing is no longer a luxury — it\'s table stakes. Confluent\'s cloud-native Kafka platform now processes trillions of events daily across thousands of organizations.</p>

<h2>The Lakehouse Architecture</h2>
<p>The convergence of data lakes and data warehouses into "lakehouse" architectures (Databricks, Apache Iceberg, Delta Lake) is enabling organizations to have a single source of truth that supports both batch analytics and real-time streaming. This eliminates the traditional trade-off between data freshness and query performance.</p>

<h2>Key Patterns</h2>
<ul>
<li><strong>Event-Driven Architecture:</strong> Every state change becomes an event that downstream systems can react to immediately</li>
<li><strong>Change Data Capture (CDC):</strong> Database changes streamed in real-time using Debezium, eliminating expensive full-table scans</li>
<li><strong>Materialized Views:</strong> Pre-computed query results that update incrementally as new data arrives</li>
</ul>

<h2>Cost Considerations</h2>
<p>Real-time isn\'t always worth the infrastructure cost. The key is identifying which data truly needs sub-second latency versus which can tolerate minutes or hours of delay. A hybrid approach — streaming for critical paths, micro-batch for everything else — often delivers the best ROI.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'RAG vs Fine-Tuning: Choosing the Right Approach for Your LLM Application',
                'slug' => 'rag-vs-fine-tuning-llm',
                'excerpt' => 'Retrieval-Augmented Generation and fine-tuning offer different trade-offs for customizing large language models. Understanding when to use each is critical for production AI systems.',
                'content' => '<p>Every team building with LLMs faces the same question: how do we make the model know about our specific data, domain, or use case? The two dominant approaches — <strong>Retrieval-Augmented Generation (RAG)</strong> and <strong>fine-tuning</strong> — each have distinct strengths, and choosing wrong can cost months of development time.</p>

<h2>RAG: Dynamic Knowledge Retrieval</h2>
<p>RAG works by retrieving relevant documents from a knowledge base at query time and including them in the LLM\'s context window. The model itself doesn\'t change — it simply has access to better information when generating responses.</p>
<p><strong>Best for:</strong> factual Q&A, documentation search, customer support, any use case where the knowledge base changes frequently.</p>
<p><strong>Advantages:</strong> No training required, knowledge stays up-to-date, easy to audit sources, lower cost.</p>

<h2>Fine-Tuning: Baked-In Knowledge</h2>
<p>Fine-tuning modifies the model\'s weights using your domain-specific data, teaching it new patterns, terminology, and reasoning styles. The knowledge becomes part of the model itself.</p>
<p><strong>Best for:</strong> specialized writing styles, domain-specific reasoning (legal, medical), structured output generation, workflow-specific tasks.</p>
<p><strong>Advantages:</strong> Lower latency (no retrieval step), better at style/tone, works with shorter prompts.</p>

<h2>The Hybrid Approach</h2>
<p>In practice, many production systems combine both: a fine-tuned model that understands the domain\'s language and reasoning patterns, augmented with RAG for access to current, factual information. This gives you the best of both worlds — domain expertise with up-to-date knowledge.</p>

<h2>Decision Framework</h2>
<ul>
<li>Data changes frequently → <strong>RAG</strong></li>
<li>Need specific output format/style → <strong>Fine-tune</strong></li>
<li>Must cite sources → <strong>RAG</strong></li>
<li>Latency-critical → <strong>Fine-tune</strong></li>
<li>Complex domain reasoning + current data → <strong>Both</strong></li>
</ul>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Edge Computing Meets AI: Processing Intelligence Where Data Lives',
                'slug' => 'edge-computing-meets-ai',
                'excerpt' => 'With IoT devices generating exabytes of data daily, edge AI is enabling real-time inference at the source — reducing latency, bandwidth costs, and cloud dependency.',
                'content' => '<p>The traditional model of sending all data to the cloud for processing is breaking down. With billions of IoT devices, autonomous vehicles, and smart infrastructure generating massive data streams, the economics and physics of centralized computing no longer make sense for many use cases.</p>

<h2>Why Edge AI Matters</h2>
<p><strong>Latency:</strong> An autonomous vehicle can\'t wait 100ms for a cloud round-trip to decide whether to brake. Edge inference happens in single-digit milliseconds.</p>
<p><strong>Bandwidth:</strong> A single factory floor with 1,000 sensors can generate terabytes of data daily. Sending it all to the cloud is prohibitively expensive.</p>
<p><strong>Privacy:</strong> Healthcare devices and security cameras can process sensitive data locally without it ever leaving the premises.</p>

<h2>The Technology Stack</h2>
<p>The edge AI ecosystem has matured rapidly. NVIDIA\'s Jetson platform, Google\'s Coral TPU, and Apple\'s Neural Engine bring serious compute power to devices. On the software side, frameworks like TensorFlow Lite, ONNX Runtime, and PyTorch Mobile enable model optimization for resource-constrained environments.</p>

<h2>Model Compression Techniques</h2>
<ul>
<li><strong>Quantization:</strong> Reducing model precision from 32-bit to 8-bit or even 4-bit, cutting model size by 4-8x with minimal accuracy loss</li>
<li><strong>Knowledge Distillation:</strong> Training a smaller "student" model to mimic a larger "teacher" model</li>
<li><strong>Pruning:</strong> Removing redundant neural network connections to reduce computation</li>
</ul>

<h2>Real-World Deployments</h2>
<p>Retail chains are using edge AI for real-time inventory tracking and checkout-free stores. Manufacturing plants deploy computer vision models on edge devices for quality inspection at line speed. Smart cities process traffic camera feeds locally for congestion management without streaming video to the cloud.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'The Data Mesh Revolution: Decentralizing Data Ownership at Scale',
                'slug' => 'data-mesh-revolution',
                'excerpt' => 'Data mesh shifts data architecture from centralized platforms to domain-oriented ownership, treating data as a product — and it\'s changing how large organizations think about analytics.',
                'content' => '<p>For years, organizations have tried to solve their data challenges with bigger, more centralized platforms — data warehouses, data lakes, and now lakehouses. Yet many still struggle with data quality, discoverability, and time-to-insight. <strong>Data mesh</strong>, a paradigm introduced by Zhamak Dehghani, offers a fundamentally different approach.</p>

<h2>Core Principles</h2>
<p>Data mesh is built on four pillars:</p>
<ul>
<li><strong>Domain-Oriented Ownership:</strong> The teams that produce data own it as a product, not a centralized data team</li>
<li><strong>Data as a Product:</strong> Each domain publishes discoverable, trustworthy, self-describing data products with SLAs</li>
<li><strong>Self-Serve Data Platform:</strong> A shared infrastructure layer that makes it easy for domains to build, deploy, and maintain data products</li>
<li><strong>Federated Computational Governance:</strong> Global standards enforced automatically, not through manual review processes</li>
</ul>

<h2>Why Now?</h2>
<p>Three converging trends make data mesh practical today. First, cloud-native platforms (Snowflake, Databricks, BigQuery) have reduced the infrastructure burden. Second, data contracts and schema registries enable interoperability between domains. Third, the rise of platform engineering as a discipline provides the organizational model for self-serve infrastructure.</p>

<h2>Implementation Challenges</h2>
<p>Data mesh is not a technology — it\'s an organizational and architectural paradigm. The hardest part isn\'t the tooling; it\'s convincing domain teams to take ownership of data quality and invest in publishing well-documented data products. This requires executive sponsorship, clear incentives, and a cultural shift toward treating data consumers as customers.</p>

<h2>Who Should Consider Data Mesh?</h2>
<p>Data mesh works best for large organizations (500+ engineers) with multiple distinct business domains. Smaller teams or single-product companies are usually better served by a centralized approach. The overhead of domain boundaries isn\'t worth it if you only have one domain.</p>',
                'status' => 'published',
                'published_at' => now(),
            ],
        ];

        foreach ($posts as $postData) {
            Post::updateOrCreate(
                ['slug' => $postData['slug']],
                $postData
            );
        }

        // Attach categories to posts
        $categoryMap = [
            'rise-of-agentic-ai' => ['artificial-intelligence', 'software-engineering'],
            'real-time-data-pipelines' => ['big-data', 'cloud-computing'],
            'rag-vs-fine-tuning-llm' => ['artificial-intelligence'],
            'edge-computing-meets-ai' => ['artificial-intelligence', 'cloud-computing'],
            'data-mesh-revolution' => ['big-data', 'software-engineering'],
        ];

        foreach ($categoryMap as $postSlug => $categorySlugs) {
            $post = Post::where('slug', $postSlug)->first();
            if ($post) {
                $categoryIds = Category::whereIn('slug', $categorySlugs)->pluck('id');
                $post->categories()->sync($categoryIds);
            }
        }
    }
}
