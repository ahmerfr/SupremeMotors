<script setup lang="js">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

// Form setup
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login" />
    <FrontLayout>
        <!-- <div
            class="absolute top-0 left-0 bg-gradient-to-b from-[#225282] via-[#225282] to-[#8a2527] bottom-0 leading-5 h-full w-full overflow-hidden">
        </div> -->
        <div class=" bg-gradient-to-b from-[#225282] via-[#225282] to-[#8a2527] relative min-h-screen sm:flex sm:flex-row justify-center bg-transparent shadow-xl">
            <div class="flex-col flex self-center lg:px-10 sm:max-w-4xl xl:max-w-md z-10">
                <div class="self-start hidden lg:flex flex-col text-gray-300">
                    <div class="text-center">
                        <img src="/assets/images/site-logo.png" alt="Company Logo" class="w-40 my-3" />
                    </div>
                    <h1 class="font-semibold text-4xl text-white">Welcome back</h1>
                    <p class="pr-3 text-sm opacity-90 text-white">
                        Our site specializes in the e-commerce sale of construction machinery and all types of
                        automobile spare parts.
                        Whether you're looking for heavy equipment or car parts, we've got you covered with high-quality
                        products for your needs.
                    </p>
                </div>
            </div>
            <div class="flex justify-center self-center z-10">
                <div
                    class="p-12 bg-white mx-auto rounded-3xl w-96 shadow-2xl transition-shadow duration-300 ease-in-out">
                    <!-- Company Logo -->

                    <div class="mb-7">
                        <h3 class="font-semibold text-2xl text-gray-800">Sign In</h3>
                    </div>
                    <div class="space-y-6">
                        <!-- Email Input -->
                        <div class="grid gap-2">
                            <Label for="email" class="text-black">Email address</Label>
                            <Input id="email" type="email" required autofocus v-model="form.email"
                                 placeholder="email@example.com" autocomplete="email"
                                 class="bg-transparent" style="border: 2px solid #d7dadd;" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Password Input -->
                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="text-black">Password</Label>
                                <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm">
                                    Forgot password?
                                </TextLink>
                            </div>
                            <Input id="password" type="password" required v-model="form.password" placeholder="Password"
                                style="border: 2px solid #d7dadd;" autocomplete="current-password"
                                class="bg-transparent" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Remember Me Checkbox -->
                        <div class="flex items-center justify-between">
                            <Label for="remember" class="flex items-center space-x-3">
                                <Checkbox id="remember" v-model:checked="form.remember" />
                                <span class="text-black">Remember me</span>
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button type="submit" class="mt-4 w-full text-white" :disabled="form.processing"
                            @click="submit">
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            Sign in
                        </Button>

                        <!-- Register Link -->
                        <div class="text-center mt-4">
                            <p class="text-sm text-gray-600">
                                Don't have an account?
                                <TextLink :href="route('register')" class="font-medium text-black" style="color:black">
                                    Register here
                                </TextLink>
                            </p>
                        </div>

                        <!-- Google Login Button -->
                        <div class="flex justify-center gap-5 w-full mt-5">
                            <a :href="route('auth.google.redirect')"
                                class="w-full flex items-center justify-center mb-6 md:mb-0 border border-gray-300 hover:border-gray-900 hover:bg-gray-900 text-sm text-gray-500 p-3 rounded-lg tracking-wide font-medium cursor-pointer transition ease-in duration-500">
                                <svg class="w-4 mr-2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="#EA4335"
                                        d="M5.266 9.765A7.077 7.077 0 0 1 12 4.909c1.69 0 3.218.6 4.418 1.582L19.91 3C17.782 1.145 15.055 0 12 0 7.27 0 3.198 2.698 1.24 6.65l4.026 3.115Z" />
                                    <path fill="#34A853"
                                        d="M16.04 18.013c-1.09.703-2.474 1.078-4.04 1.078a7.077 7.077 0 0 1-6.723-4.823l-4.04 3.067A11.965 11.965 0 0 0 12 24c2.933 0 5.735-1.043 7.834-3l-3.793-2.987Z" />
                                    <path fill="#4A90E2"
                                        d="M19.834 21c2.195-2.048 3.62-5.096 3.62-9 0-.71-.109-1.473-.272-2.182H12v4.637h6.436c-.317 1.559-1.17 2.766-2.395 3.558L19.834 21Z" />
                                    <path fill="#FBBC05"
                                        d="M5.277 14.268A7.12 7.12 0 0 1 4.909 12c0-.782.125-1.533.357-2.235L1.24 6.65A11.934 11.934 0 0 0 0 12c0 1.92.445 3.73 1.237 5.335l4.04-3.067Z" />
                                </svg>
                                Google
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- <svg class="absolute bottom-0 left-0 z-0 md:z-auto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#fff" fill-opacity="1"
                d="M0,0L40,42.7C80,85,160,171,240,197.3C320,224,400,192,480,154.7C560,117,640,75,720,74.7C800,75,880,117,960,154.7C1040,192,1120,224,1200,213.3C1280,203,1360,149,1400,122.7L1440,96L1440,320L1400,320C1360,320,1280,320,1200,320C1120,320,1040,320,960,320C880,320,800,320,720,320C640,320,560,320,480,320C400,320,320,320,240,320C160,320,80,320,40,320L0,320Z">
            </path>
        </svg> -->
    </FrontLayout>
</template>