<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Phone, User, Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';
import FrontLayout from '@/layouts/app/FrontLayout.vue';
import { ref } from 'vue';

// Form with added phone field
const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

// For password visibility toggle
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPasswordVisibility = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />
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
                    <h1 class="font-semibold text-4xl text-white">Join our platform</h1>
                    <p class="pr-3 text-sm opacity-90 text-white mt-4">
                        Create an account to access our full catalog of construction machinery and automobile spare parts.
                        Get exclusive deals, track your orders, and enjoy a seamless shopping experience.
                    </p>
                </div>
            </div>
            <div class="flex justify-center self-center z-10">
                <div
                    class="p-10 bg-white mx-auto rounded-3xl w-[480px] shadow-2xl transition-shadow duration-300 ease-in-out hover:shadow-lg">
                    <div class="mb-7">
                        <h3 class="font-semibold text-2xl text-gray-800">Create Account</h3>
                        <p class="text-gray-500 text-sm mt-2">Fill in your details to get started</p>
                    </div>

                    <!-- Google Login Button -->
                    <a :href="route('auth.google.redirect')"
                        class="w-full flex items-center justify-center mb-6 border border-gray-300 hover:border-gray-900 hover:bg-gray-100 text-sm text-gray-600 p-3 rounded-lg tracking-wide font-medium cursor-pointer transition ease-in duration-300">
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
                        Continue with Google
                    </a>

                    <div class="flex items-center justify-center space-x-2 my-5">
                        <span class="h-px bg-gray-300 flex-1"></span>
                        <span class="text-gray-500 text-sm">or register with email</span>
                        <span class="h-px bg-gray-300 flex-1"></span>
                    </div>

                    <form @submit.prevent="submit" class="flex flex-col gap-4">
                        <div class="grid gap-4">
                            <!-- Name and Email Fields on the same line -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Name Field -->
                                <div class="grid gap-2">
                                    <Label for="name" class="text-gray-700 font-medium">Full Name</Label>
                                    <div class="relative">
                                        <User class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                        <Input id="name" type="text"  autofocus :tabindex="1" autocomplete="name"
                                            v-model="form.name" placeholder="John Doe"
                                            style="border: 2px solid #d7dadd;"
                                            class="bg-transparent pl-10 transition duration-300 ease-in focus:border-blue-500 focus:ring focus:ring-blue-200" />
                                    </div>
                                    <InputError :message="form.errors.name" />
                                </div>

                                <!-- Email Field -->
                                <div class="grid gap-2">
                                    <Label for="email" class="text-gray-700 font-medium">Email Address</Label>
                                    <div class="relative">
                                        <Mail class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                        <Input id="email" type="email"  :tabindex="2" autocomplete="email"
                                            v-model="form.email" placeholder="email@example.com" style="border: 2px solid #d7dadd;"
                                        class="bg-transparent pl-10 transition duration-300 ease-in focus:border-blue-500 focus:ring focus:ring-blue-200" />
                                    </div>
                                    <InputError :message="form.errors.email" />
                                </div>
                            </div>

                            <!-- Phone Field -->
                            <div class="grid gap-2">
                                <Label for="phone" class="text-gray-700 font-medium">Phone Number</Label>
                                <div class="relative">
                                    <Phone class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                    <Input id="phone" type="tel"  :tabindex="3" autocomplete="tel"
                                        v-model="form.phone" placeholder="+1 (555) 123-4567" style="border: 2px solid #d7dadd;"
                                        class="bg-transparent pl-10 transition duration-300 ease-in focus:border-blue-500 focus:ring focus:ring-blue-200" />
                                </div>
                                <InputError :message="form.errors.phone" />
                            </div>

                            <!-- Password and Confirmation on the same line -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Password Field -->
                                <div class="grid gap-2">
                                    <Label for="password" class="text-gray-700 font-medium">Password</Label>
                                    <div class="relative">
                                        <Lock class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                        <Input id="password" :type="showPassword ? 'text' : 'password'"  :tabindex="4"
                                            autocomplete="new-password" v-model="form.password" placeholder="Password"
                                            style="border: 2px solid #d7dadd;"
                                        class="bg-transparent pl-10 pr-10 transition duration-300 ease-in focus:border-blue-500 focus:ring focus:ring-blue-200" />
                                        <button type="button" @click="togglePasswordVisibility"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <Eye v-if="!showPassword" class="h-4 w-4" />
                                            <EyeOff v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.password" />
                                </div>

                                <!-- Confirm Password Field -->
                                <div class="grid gap-2">
                                    <Label for="password_confirmation" class="text-gray-700 font-medium">Confirm Password</Label>
                                    <div class="relative">
                                        <Lock class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                        <Input id="password_confirmation" :type="showConfirmPassword ? 'text' : 'password'"
                                             :tabindex="5" autocomplete="new-password"
                                            v-model="form.password_confirmation" placeholder="Confirm"
                                            style="border: 2px solid #d7dadd;"
                                        class="bg-transparent pl-10 pr-10 transition duration-300 ease-in focus:border-blue-500 focus:ring focus:ring-blue-200" />
                                        <button type="button" @click="toggleConfirmPasswordVisibility"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                                            <EyeOff v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                            <Button type="submit" class="mt-4 w-full text-white" :disabled="form.processing"
                                @click="submit">
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                <span>Create account</span>
                            </Button>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center text-sm text-gray-600 mt-4">
                            Already have an account?
                            <TextLink style="color:black" :href="route('login')" class="text-blue-600 hover:text-blue-800 font-medium ml-1 text-black" :tabindex="7">
                                Log in
                            </TextLink>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </FrontLayout>
</template>